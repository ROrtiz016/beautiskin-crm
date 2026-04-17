<?php

namespace App\Support;

use App\Services\AppointmentPolicyEnforcer;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class ReportDateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} from, to (clinic tz end of day), rangeStart, rangeEnd (app tz for DB)
     */
    public static function resolve(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $tz = AppointmentPolicyEnforcer::clinicTimezone();
        $appTz = config('app.timezone');

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'), $tz)->endOfDay()
            : now($tz)->endOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'), $tz)->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        $rangeStart = $from->copy()->timezone($appTz);
        $rangeEnd = $to->copy()->timezone($appTz);

        return [$from, $to, $rangeStart, $rangeEnd];
    }
}
