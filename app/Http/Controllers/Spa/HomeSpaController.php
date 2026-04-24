<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\HomeController;
use Illuminate\Http\JsonResponse;

class HomeSpaController extends HomeController
{
    public function show(): JsonResponse
    {
        return response()->json($this->homeWelcomePayload());
    }
}
