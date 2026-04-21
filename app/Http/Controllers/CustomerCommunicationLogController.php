<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CommunicationRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerCommunicationLogController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
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

        $return = (string) $request->input('return_to', '');

        return $return === 'timeline'
            ? redirect()->route('customers.timeline.show', $customer)->with('status', 'Communication logged.')
            : redirect()->route('customers.show', $customer)->with('status', 'Communication logged.');
    }
}
