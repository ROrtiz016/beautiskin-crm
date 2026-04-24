<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\ReportingController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsSpaController extends ReportingController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->reportsIndexPayload($request));
    }
}
