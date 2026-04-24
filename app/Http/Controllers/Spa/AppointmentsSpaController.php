<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\AppointmentWebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentsSpaController extends AppointmentWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->appointmentIndexPayload($request));
    }
}
