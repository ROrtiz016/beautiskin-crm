<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\AdminControlBoardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminControlBoardSpaController extends AdminControlBoardController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->controlBoardPayload($request));
    }
}
