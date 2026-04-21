<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Support\CustomerTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerTimelineNoteController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
        ]);

        CustomerTimeline::record(
            $customer,
            CustomerActivity::EVENT_NOTE_ADDED,
            $validated['summary'],
            $request->user()->id,
            null,
            CustomerActivity::CATEGORY_NOTE,
        );

        if ((string) $request->input('return_to') === 'timeline') {
            return redirect()
                ->route('customers.timeline.show', $customer)
                ->with('status', 'Timeline note added.');
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Timeline note added.');
    }
}
