<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AppointmentPolicyEnforcer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        $appointments = Appointment::query()
            ->with(['customer', 'staffUser', 'customerMembership.membership', 'services.service'])
            ->orderBy('scheduled_at')
            ->paginate(20);

        return response()->json($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'customer_id' => ['required', 'exists:customers,id'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'customer_membership_id' => ['nullable', 'exists:customer_memberships,id'],
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'services' => ['array'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ], AppointmentPolicyEnforcer::depositRulesForRequest()));

        $dateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($dateKey);

        $depositPaid = AppointmentPolicyEnforcer::depositPaidFromValidated($validated);
        $depositAmount = $depositPaid ? AppointmentPolicyEnforcer::defaultDepositAmount() : null;

        $appointment = DB::transaction(function () use ($validated, $depositPaid, $depositAmount) {
            $appointment = Appointment::create([
                'customer_id' => $validated['customer_id'],
                'staff_user_id' => $validated['staff_user_id'] ?? null,
                'customer_membership_id' => $validated['customer_membership_id'] ?? null,
                'scheduled_at' => $validated['scheduled_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => $validated['status'] ?? 'booked',
                'notes' => $validated['notes'] ?? null,
                'total_amount' => 0,
                'deposit_amount' => $depositAmount,
                'deposit_paid' => $depositPaid,
            ]);

            $total = 0.0;

            foreach ($validated['services'] ?? [] as $serviceLine) {
                $service = \App\Models\Service::query()->findOrFail($serviceLine['service_id']);
                $quantity = $serviceLine['quantity'] ?? 1;
                $lineTotal = round(((float) $service->price) * $quantity, 2);
                $total = round($total + $lineTotal, 2);

                $appointment->services()->create([
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'quantity' => $quantity,
                    'unit_price' => $service->price,
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ]);
            }

            $appointment->update(['total_amount' => number_format($total, 2, '.', '')]);

            return $appointment;
        });

        return response()->json(
            $appointment->fresh()->load(['customer', 'staffUser', 'customerMembership.membership', 'services.service']),
            201
        );
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['customer', 'staffUser', 'customerMembership.membership', 'services.service']);

        return response()->json($appointment);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'customer_membership_id' => ['nullable', 'exists:customer_memberships,id'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'cancelled' && $appointment->status !== 'cancelled') {
            AppointmentPolicyEnforcer::assertCanMarkCancelled($appointment);
        }

        if (isset($validated['scheduled_at'])) {
            $newKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
            $oldKey = AppointmentPolicyEnforcer::appointmentDateKey($appointment->scheduled_at);
            if ($newKey !== $oldKey) {
                AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($newKey, $appointment->id);
            }
        }

        $appointment->update($validated);

        return response()->json($appointment->fresh()->load(['customer', 'staffUser', 'customerMembership.membership', 'services']));
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(status: 204);
    }
}
