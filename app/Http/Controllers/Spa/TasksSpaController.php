<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\TaskWebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TasksSpaController extends TaskWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->tasksIndexPayload($request));
    }
}
