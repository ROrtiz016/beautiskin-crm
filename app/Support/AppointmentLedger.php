<?php

namespace App\Support;

use App\Models\Appointment;

final class AppointmentLedger
{
    /**
     * Persist a deposit collected at booking as its own ledger row (separate from visit line totals).
     */
    public static function recordBookingDepositIfPaid(Appointment $appointment, bool $depositPaid, ?string $depositAmount, ?int $userId): void
    {
        if (! $depositPaid) {
            return;
        }

        $amount = $depositAmount !== null ? (float) $depositAmount : 0.0;
        if ($amount <= 0) {
            return;
        }

        $appointment->paymentEntries()->create([
            'amount' => number_format($amount, 2, '.', ''),
            'entry_type' => 'deposit',
            'note' => 'Recorded at booking',
            'recorded_by_user_id' => $userId,
        ]);
    }
}
