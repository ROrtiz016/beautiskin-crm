<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\MembershipWebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipsSpaController extends MembershipWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->membershipsIndexPayload($request));
    }
}
