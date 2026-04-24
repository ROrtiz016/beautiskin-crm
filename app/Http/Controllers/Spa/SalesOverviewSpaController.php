<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\SalesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOverviewSpaController extends SalesController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->salesIndexPayload($request));
    }
}
