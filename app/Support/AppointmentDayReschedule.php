<?php

namespace App\Support;

use App\Models\Appointment;
use App\Services\AppointmentPolicyEnforcer;
use Carbon\Carbon;

final class AppointmentDayReschedule
{
    /**
     * Move the appointment to another calendar day while preserving clock times (clinic-local).
     */
    public static function moveToDate(Appointment $appointment, Carbon $targetDate): void
    {
        $appointment->loadMissing('services');

        $scheduledAt = $appointment->scheduled_at
            ? $appointment->scheduled_at->copy()->setDate($targetDate->year, $targetDate->month, $targetDate->day)
            : $targetDate->copy()->setTime(9, 0);
        $endsAt = $appointment->ends_at
            ? $appointment->ends_at->copy()->setDate($targetDate->year, $targetDate->month, $targetDate->day)
            : null;

        $payload = [
            'customer_id' => $appointment->customer_id,
            'staff_user_id' => $appointment->staff_user_id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'services' => $appointment->services->map(fn ($service) => [
                'service_id' => $service->service_id,
                'quantity' => $service->quantity,
            ])->values()->all(),
        ];

        AppointmentStaffConflicts::assertNoOverlap($payload, $appointment);

        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded(
            AppointmentPolicyEnforcer::appointmentDateKey($payload['scheduled_at']),
            $appointment->id
        );

        $appointment->update([
            'scheduled_at' => $payload['scheduled_at'],
            'ends_at' => $payload['ends_at'],
        ]);
    }
}
