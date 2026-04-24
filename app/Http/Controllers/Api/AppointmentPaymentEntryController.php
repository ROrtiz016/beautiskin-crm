<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPaymentEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentPaymentEntryController extends Controller
{
    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_type' => ['required', Rule::in(['deposit', 'payment', 'refund', 'adjustment'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $validated['amount'];
        if ($validated['entry_type'] === 'refund') {
            $amount = -abs($amount);
        } else {
            $amount = abs($amount);
        }

        $entry = $appointment->paymentEntries()->create([
            'amount' => number_format($amount, 2, '.', ''),
            'entry_type' => $validated['entry_type'],
            'note' => $validated['note'] ?? null,
            'recorded_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(
            $entry->fresh()->load('appointment:id,customer_id,scheduled_at'),
            201
        );
    }

    public function destroy(AppointmentPaymentEntry $appointmentPaymentEntry): JsonResponse
    {
        $appointmentPaymentEntry->delete();

        return response()->json(status: 204);
    }
}
