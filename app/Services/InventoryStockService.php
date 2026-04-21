<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class InventoryStockService
{
    /**
     * Decrement stock for each line on a visit that has inventory tracking enabled.
     */
    public static function deductForCompletedAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['services.service']);

        DB::transaction(function () use ($appointment) {
            foreach ($appointment->services as $line) {
                $service = $line->service;
                if (! $service || ! $service->track_inventory) {
                    continue;
                }

                self::adjustStockLocked($service->id, -((int) $line->quantity));
            }
        });
    }

    /**
     * Add stock back when a completed visit is reverted to another status.
     */
    public static function restoreForCompletedAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['services.service']);

        DB::transaction(function () use ($appointment) {
            foreach ($appointment->services as $line) {
                $service = $line->service;
                if (! $service || ! $service->track_inventory) {
                    continue;
                }

                self::adjustStockLocked($service->id, (int) $line->quantity);
            }
        });
    }

    /**
     * Decrement stock for a single retail line (e.g. added after checkout). Caller should validate quantity.
     */
    public static function deductForService(Service $service, int $quantity): void
    {
        if (! $service->track_inventory || $quantity < 1) {
            return;
        }

        DB::transaction(function () use ($service, $quantity) {
            self::adjustStockLocked($service->id, -$quantity);
        });
    }

    private static function adjustStockLocked(int $serviceId, int $delta): void
    {
        $row = Service::query()
            ->whereKey($serviceId)
            ->where('track_inventory', true)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            return;
        }

        $next = max(0, (int) $row->stock_quantity + $delta);
        $row->update(['stock_quantity' => $next]);
    }
}
