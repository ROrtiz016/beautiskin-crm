<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\ServiceWebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicesSpaController extends ServiceWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->servicesIndexPayload($request));
    }
}
