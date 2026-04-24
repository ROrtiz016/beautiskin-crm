<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class AppointmentStaffConflicts
{
    public static function estimatedDurationMinutes(array $serviceLines): int
    {
        $total = 0;

        foreach ($serviceLines as $line) {
            $service = Service::query()->find((int) ($line['service_id'] ?? 0));
            if (! $service) {
                continue;
            }

            $total += ((int) $service->duration_minutes) * max(1, (int) ($line['quantity'] ?? 1));
        }

        return max($total, 60);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertNoOverlap(array $validated, ?Appointment $ignoreAppointment = null): void
    {
        $staffUserId = (int) ($validated['staff_user_id'] ?? 0);
        if ($staffUserId === 0) {
            return;
        }

        $start = Carbon::parse($validated['scheduled_at']);
        $end = ! empty($validated['ends_at'])
            ? Carbon::parse($validated['ends_at'])
            : $start->copy()->addMinutes(self::estimatedDurationMinutes($validated['services'] ?? []));

        $conflict = Appointment::query()
            ->where('staff_user_id', $staffUserId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($ignoreAppointment, function (Builder $query) use ($ignoreAppointment) {
                $query->where('id', '!=', $ignoreAppointment->id);
            })
            ->where(function (Builder $query) use ($start, $end) {
                $query
                    ->where('scheduled_at', '<', $end)
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(scheduled_at, INTERVAL 60 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')]);
            })
            ->with(['customer:id,first_name,last_name'])
            ->first();

        if (! $conflict) {
            return;
        }

        $name = trim((string) ($conflict->customer?->first_name.' '.$conflict->customer?->last_name));

        throw ValidationException::withMessages([
            'scheduled_at' => [
                'This staff member already has an overlapping appointment'
                .($name !== '' ? ' with '.$name : '')
                .' at '
                .optional($conflict->scheduled_at)->format('g:i A')
                .'.',
            ],
        ]);
    }
}
