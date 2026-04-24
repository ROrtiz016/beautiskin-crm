<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\InventoryWebController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventorySpaController extends InventoryWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->inventoryIndexPayload($request));
    }
}
