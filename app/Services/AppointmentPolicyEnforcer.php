<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class AppointmentPolicyEnforcer
{
    public static function clinicTimezone(): string
    {
        $tz = (string) ClinicSetting::current()->clinic_timezone;

        return $tz !== '' ? $tz : (string) config('app.timezone');
    }

    public static function appointmentDateKey(mixed $scheduledAt): string
    {
        return Carbon::parse($scheduledAt)->timezone(self::clinicTimezone())->toDateString();
    }

    /**
     * @return array{0: Carbon, 1: Carbon} Start and end of the clinic-local day in the app timezone for DB queries.
     */
    public static function clinicDayBounds(string $ymd): array
    {
        $clinicTz = self::clinicTimezone();
        $appTz = (string) config('app.timezone');
        $start = Carbon::createFromFormat('Y-m-d', $ymd, $clinicTz)->startOfDay()->timezone($appTz);
        $end = Carbon::createFromFormat('Y-m-d', $ymd, $clinicTz)->endOfDay()->timezone($appTz);

        return [$start, $end];
    }

    public static function countActiveBookingsOnClinicDate(string $ymd, ?int $ignoreAppointmentId = null): int
    {
        [$start, $end] = self::clinicDayBounds($ymd);

        return Appointment::query()
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->count();
    }

    public static function assertMaxBookingsNotExceeded(string $ymd, ?int $ignoreAppointmentId = null): void
    {
        $max = ClinicSetting::current()->max_bookings_per_day;
        if ($max === null || $max < 1) {
            return;
        }

        $count = self::countActiveBookingsOnClinicDate($ymd, $ignoreAppointmentId);
        if ($count >= $max) {
            throw ValidationException::withMessages([
                'scheduled_at' => sprintf(
                    'This day has reached the maximum of %d active appointment(s) allowed by clinic policy.',
                    $max
                ),
            ]);
        }
    }

    public static function assertCanMarkCancelled(Appointment $appointment): void
    {
        $hoursRequired = (int) ClinicSetting::current()->appointment_cancellation_hours;
        if ($hoursRequired < 1) {
            return;
        }

        $scheduledAt = $appointment->scheduled_at;
        if (! $scheduledAt) {
            return;
        }

        $tz = self::clinicTimezone();
        $start = $scheduledAt->copy()->timezone($tz);
        $now = now($tz);
        if ($now->greaterThanOrEqualTo($start)) {
            return;
        }

        $secondsUntilStart = $start->getTimestamp() - $now->getTimestamp();
        $hoursUntilStart = $secondsUntilStart / 3600.0;
        if ($hoursUntilStart < $hoursRequired) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cancellations must be made at least %d hour(s) before the scheduled start time.',
                    $hoursRequired
                ),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function depositRulesForRequest(): array
    {
        $required = (bool) ClinicSetting::current()->deposit_required;

        return [
            'deposit_paid' => $required ? ['required', 'accepted'] : ['sometimes', 'boolean'],
        ];
    }

    public static function depositPaidFromValidated(array $validated): bool
    {
        if (! ClinicSetting::current()->deposit_required) {
            return (bool) ($validated['deposit_paid'] ?? false);
        }

        return (bool) ($validated['deposit_paid'] ?? false);
    }

    public static function defaultDepositAmount(): ?string
    {
        $amount = ClinicSetting::current()->default_deposit_amount;
        if ($amount === null) {
            return null;
        }

        return number_format((float) $amount, 2, '.', '');
    }
}
