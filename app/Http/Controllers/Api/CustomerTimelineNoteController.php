<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Support\CustomerTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTimelineNoteController extends Controller
{
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
        ]);

        $activity = CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_NOTE_ADDED,
            $validated['summary'],
            $request->user()->id,
            null,
            CustomerActivity::CATEGORY_NOTE,
        );

        $activity->load(['user:id,name']);

        return response()->json([
            'message' => 'Timeline note added.',
            'activity' => [
                'id' => $activity->id,
                'summary' => $activity->summary,
                'category' => $activity->category,
                'event_type' => $activity->event_type,
                'created_at' => $activity->created_at,
                'user' => $activity->user,
            ],
        ], 201);
    }
}
