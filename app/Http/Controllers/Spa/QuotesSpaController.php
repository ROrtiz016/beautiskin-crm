<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\QuoteWebController;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotesSpaController extends QuoteWebController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->quotesIndexPayload($request));
    }

    public function show(Quote $quote): JsonResponse
    {
        return response()->json($this->quoteShowPayload($quote));
    }
}
