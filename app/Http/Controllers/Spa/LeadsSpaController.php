<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\LeadsController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadsSpaController extends LeadsController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->leadsIndexPayload($request));
    }
}
