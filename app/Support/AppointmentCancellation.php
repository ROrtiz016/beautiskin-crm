<?php

namespace App\Support;

use App\Models\Appointment;
use App\Services\AppointmentPolicyEnforcer;
use Illuminate\Http\Request;

final class AppointmentCancellation
{
    /**
     * @return array<string, mixed>
     */
    public static function attributesWhenChangingStatus(Request $request, Appointment $appointment, string $newStatus): array
    {
        $payload = ['status' => $newStatus];

        if ($newStatus === 'cancelled' && $appointment->status !== 'cancelled') {
            AppointmentPolicyEnforcer::assertCanMarkCancelled($appointment);
            $cancellation = $request->validate([
                'cancellation_reason' => ['required', 'string', 'max:5000'],
                'sales_follow_up_needed' => ['nullable', 'boolean'],
            ]);

            $payload['cancellation_reason'] = $cancellation['cancellation_reason'];
            $payload['sales_follow_up_needed'] = (bool) ($cancellation['sales_follow_up_needed'] ?? false);
            $payload['cancelled_by_user_id'] = $request->user()?->id;
            $payload['cancelled_at'] = now();

            return $payload;
        }

        if ($appointment->status === 'cancelled' && $newStatus !== 'cancelled') {
            $payload['cancellation_reason'] = null;
            $payload['sales_follow_up_needed'] = false;
            $payload['cancelled_by_user_id'] = null;
            $payload['cancelled_at'] = null;
        }

        return $payload;
    }
}
