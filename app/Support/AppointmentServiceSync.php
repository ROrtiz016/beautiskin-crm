<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Service;

final class AppointmentServiceSync
{
    /**
     * @param  list<array{service_id?: int, quantity?: int}>  $serviceLines
     */
    public static function sync(Appointment $appointment, array $serviceLines): void
    {
        $appointment->services()->delete();
        $total = 0.0;

        foreach ($serviceLines as $line) {
            $service = Service::query()->findOrFail((int) $line['service_id']);
            $quantity = (int) ($line['quantity'] ?? 1);
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
    }
}
