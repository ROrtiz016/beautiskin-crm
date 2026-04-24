<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\OperationsDashboardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsSpaController extends OperationsDashboardController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->operationsIndexPayload($request));
    }
}
