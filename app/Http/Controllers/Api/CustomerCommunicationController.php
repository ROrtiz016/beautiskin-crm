<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CommunicationRecorder;
use App\Services\OutboundCustomerMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerCommunicationController extends Controller
{
    public function store(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::in(['call', 'email', 'sms'])],
            'summary' => ['required', 'string', 'max:5000'],
        ]);

        CommunicationRecorder::recordManualNote(
            $customer,
            (int) $request->user()->id,
            $validated['channel'],
            $validated['summary'],
        );

        return response()->json([
            'message' => 'Communication logged.',
        ], 201);
    }

    public function storeTemplated(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(['follow_up', 'no_show', 'reminder'])],
            'channel' => ['required', 'string', Rule::in(['email', 'sms'])],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(fn ($q) => $q->where('customer_id', $customer->id)),
            ],
        ]);

        try {
            OutboundCustomerMessageService::sendTemplated(
                $customer,
                (int) $request->user()->id,
                $validated['channel'],
                $validated['template'],
                isset($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Message sent.',
        ], 201);
    }
}
