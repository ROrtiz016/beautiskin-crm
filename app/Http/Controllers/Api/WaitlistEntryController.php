<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitlistEntry;
use App\Support\ContactMethod;
use App\Support\LeadSource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaitlistEntryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'preferred_date' => ['required', 'date'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'preferred_end_time' => ['nullable', 'date_format:H:i', 'after:preferred_start_time'],
            'notes' => ['nullable', 'string'],
            'lead_source' => ['nullable', 'string', Rule::in(LeadSource::KEYS)],
        ]);

        $leadSource = $validated['lead_source'] ?? 'unknown';
        if (! is_string($leadSource) || ! in_array($leadSource, LeadSource::KEYS, true)) {
            $leadSource = 'unknown';
        }

        $entry = WaitlistEntry::query()->create([
            'customer_id' => $validated['customer_id'],
            'service_id' => $validated['service_id'] ?? null,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'preferred_date' => $validated['preferred_date'],
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'preferred_end_time' => $validated['preferred_end_time'] ?? null,
            'status' => 'waiting',
            'lead_source' => $leadSource,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(
            $entry->fresh()->load(['customer:id,first_name,last_name', 'service:id,name', 'staffUser:id,name']),
            201
        );
    }

    public function updateStatus(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['waiting', 'booked', 'cancelled'])],
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'cancelled') {
            $updates['contacted_at'] = null;
            $updates['contact_method'] = null;
            $updates['contact_notes'] = null;
            $updates['contacted_by_user_id'] = null;
        }

        $waitlistEntry->update($updates);

        return response()->json([
            'message' => 'Waitlist entry updated.',
            'entry' => $waitlistEntry->fresh()->load(['customer:id,first_name,last_name', 'service:id,name', 'staffUser:id,name']),
        ]);
    }

    public function recordContact(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        $validated = $request->validate([
            'contact_method' => ['required', 'string', Rule::in(ContactMethod::KEYS)],
            'contact_notes' => ['required', 'string', 'min:1', 'max:10000'],
            'contacted_at' => ['required', 'date'],
        ]);

        $contactedAt = Carbon::parse($validated['contacted_at'], config('app.timezone'));

        $waitlistEntry->update([
            'status' => 'contacted',
            'contacted_at' => $contactedAt,
            'contact_method' => $validated['contact_method'],
            'contact_notes' => $validated['contact_notes'] ?? null,
            'contacted_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Contact logged and lead marked as contacted.',
            'entry' => $waitlistEntry->fresh()->load([
                'customer:id,first_name,last_name',
                'service:id,name',
                'staffUser:id,name',
                'contactedBy:id,name',
            ]),
        ]);
    }
}
