<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\ActivityFeedController;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivitySpaController extends ActivityFeedController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->activityIndexPayload($request));
    }

    public function customerTimeline(Request $request, Customer $customer): JsonResponse
    {
        return response()->json($this->customerTimelinePayload($request, $customer));
    }
}
