<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\AppointmentDayReschedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentRescheduleController extends Controller
{
    public function __invoke(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse($validated['target_date']);

        AppointmentDayReschedule::moveToDate($appointment, $targetDate);

        $appointment->refresh();

        return response()->json([
            'status' => 'ok',
            'date' => optional($appointment->scheduled_at)->toDateString(),
            'month' => optional($appointment->scheduled_at)->format('Y-m'),
            'appointment_id' => $appointment->id,
        ]);
    }
}
