<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\SalesOpportunityController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineSpaController extends SalesOpportunityController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->pipelineIndexPayload($request));
    }
}
