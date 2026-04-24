<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AppointmentPolicyEnforcer;
use App\Services\InventoryStockService;
use App\Support\AppointmentCancellation;
use App\Support\AppointmentLedger;
use App\Support\AppointmentServiceSync;
use App\Support\AppointmentStaffConflicts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        $appointments = Appointment::query()
            ->withSum('paymentEntries', 'amount')
            ->with(['customer', 'staffUser', 'customerMembership.membership', 'services.service', 'cancelledBy:id,name', 'quote:id,title,status', 'paymentEntries'])
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
            'quote_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('quotes', 'id')->where(fn ($q) => $q->where('customer_id', (int) $request->input('customer_id'))),
            ],
        ], AppointmentPolicyEnforcer::depositRulesForRequest()));

        AppointmentStaffConflicts::assertNoOverlap($validated);

        $dateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($dateKey);

        $depositPaid = AppointmentPolicyEnforcer::depositPaidFromValidated($validated);
        $depositAmount = $depositPaid ? AppointmentPolicyEnforcer::defaultDepositAmount() : null;

        $appointment = DB::transaction(function () use ($validated, $depositPaid, $depositAmount, $request) {
            $appointment = Appointment::create([
                'customer_id' => $validated['customer_id'],
                'staff_user_id' => $validated['staff_user_id'] ?? null,
                'customer_membership_id' => $validated['customer_membership_id'] ?? null,
                'quote_id' => $validated['quote_id'] ?? null,
                'scheduled_at' => $validated['scheduled_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => $validated['status'] ?? 'booked',
                'arrived_confirmed' => false,
                'notes' => $validated['notes'] ?? null,
                'total_amount' => number_format(0, 2, '.', ''),
                'deposit_amount' => $depositAmount,
                'deposit_paid' => $depositPaid,
            ]);

            if (! empty($validated['services'])) {
                AppointmentServiceSync::sync($appointment, $validated['services']);
            }

            AppointmentLedger::recordBookingDepositIfPaid($appointment, $depositPaid, $depositAmount, $request->user()?->id);

            return $appointment;
        });

        return response()->json(
            $appointment->fresh()->load(['customer', 'staffUser', 'customerMembership.membership', 'services.service', 'cancelledBy:id,name', 'quote', 'paymentEntries']),
            201
        );
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['customer', 'staffUser', 'customerMembership.membership', 'services.service', 'cancelledBy:id,name', 'quote', 'paymentEntries']);

        return response()->json($appointment);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->loadMissing('services');

        $validated = $request->validate([
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'customer_membership_id' => ['nullable', 'exists:customer_memberships,id'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['booked', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string'],
            'arrived_confirmed' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'array', 'min:1'],
            'services.*.service_id' => ['required_with:services', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['scheduled_at'])) {
            $newKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
            $oldKey = AppointmentPolicyEnforcer::appointmentDateKey($appointment->scheduled_at);
            if ($newKey !== $oldKey) {
                AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($newKey, $appointment->id);
            }
        }

        if (isset($validated['status'])) {
            $statusPayload = AppointmentCancellation::attributesWhenChangingStatus(
                $request,
                $appointment,
                $validated['status']
            );
            $validated = array_merge($validated, $statusPayload);
        }

        $needsConflictCheck = isset($validated['staff_user_id'])
            || isset($validated['scheduled_at'])
            || isset($validated['ends_at'])
            || isset($validated['services']);

        if ($needsConflictCheck && $appointment->scheduled_at) {
            $mergeForConflict = [
                'scheduled_at' => $validated['scheduled_at'] ?? $appointment->scheduled_at->format('Y-m-d H:i:s'),
                'ends_at' => array_key_exists('ends_at', $validated)
                    ? $validated['ends_at']
                    : ($appointment->ends_at?->format('Y-m-d H:i:s')),
                'staff_user_id' => array_key_exists('staff_user_id', $validated)
                    ? $validated['staff_user_id']
                    : $appointment->staff_user_id,
                'services' => $validated['services']
                    ?? $appointment->services->map(fn ($s) => [
                        'service_id' => $s->service_id,
                        'quantity' => $s->quantity,
                    ])->values()->all(),
            ];
            AppointmentStaffConflicts::assertNoOverlap($mergeForConflict, $appointment);
        }

        $servicesToSync = $validated['services'] ?? null;
        unset($validated['services']);

        $appointment->update($validated);

        if ($servicesToSync !== null && $appointment->fresh()->status !== 'completed') {
            AppointmentServiceSync::sync($appointment, $servicesToSync);
        }

        return response()->json($appointment->fresh()->load([
            'customer',
            'staffUser',
            'customerMembership.membership',
            'services',
            'cancelledBy:id,name',
            'paymentEntries',
        ]));
    }

    public function storeRetailLine(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->status !== 'completed') {
            return response()->json([
                'message' => 'Retail items can only be added to completed visits.',
            ], 422);
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $service = Service::query()
            ->whereKey($validated['service_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $service->eligibleForRetailSaleOnVisit()) {
            return response()->json([
                'message' => 'That catalog item cannot be sold as retail on a completed visit.',
            ], 422);
        }

        $quantity = (int) $validated['quantity'];

        if ($service->track_inventory && (int) $service->stock_quantity < $quantity) {
            return response()->json([
                'message' => 'Insufficient stock for '.$service->name.'.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($appointment, $service, $quantity) {
                $locked = Service::query()->whereKey($service->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->is_active) {
                    throw ValidationException::withMessages([
                        'service_id' => 'That item is no longer available.',
                    ]);
                }

                if ($locked->track_inventory && (int) $locked->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Insufficient stock for '.$locked->name.'.',
                    ]);
                }

                $unit = (float) $locked->price;
                $lineTotal = round($unit * $quantity, 2);

                $appointment->services()->create([
                    'service_id' => $locked->id,
                    'service_name' => $locked->name,
                    'duration_minutes' => (int) $locked->duration_minutes,
                    'quantity' => $quantity,
                    'unit_price' => number_format($unit, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ]);

                InventoryStockService::deductForService($locked, $quantity);

                $appointment->refresh();
                $sum = round((float) $appointment->services()->sum('line_total'), 2);
                $appointment->update([
                    'total_amount' => number_format($sum, 2, '.', ''),
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Unable to add retail line.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(
            $appointment->fresh()->load([
                'customer',
                'staffUser',
                'services.service',
                'paymentEntries',
            ])
        );
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(status: 204);
    }
}
