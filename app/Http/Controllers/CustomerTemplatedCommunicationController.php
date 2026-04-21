<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\OutboundCustomerMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerTemplatedCommunicationController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(['follow_up', 'no_show', 'reminder'])],
            'channel' => ['required', 'string', Rule::in(['email', 'sms'])],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'return_to' => ['nullable', 'string'],
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
            return redirect()
                ->back()
                ->withInput()
                ->withErrors($e->errors());
        }

        $return = (string) ($validated['return_to'] ?? '');

        return $return === 'timeline'
            ? redirect()->route('customers.timeline.show', $customer)->with('status', 'Message sent.')
            : redirect()->route('customers.show', $customer)->with('status', 'Message sent.');
    }
}
