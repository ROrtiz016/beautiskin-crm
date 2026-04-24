<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Service;
use App\Models\TreatmentPackage;
use App\Services\QuoteTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteWebController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        return view('quotes.index', $this->quotesIndexPayload($request));
    }

    /**
     * @return array{quotes: \Illuminate\Contracts\Pagination\LengthAwarePaginator, customers: \Illuminate\Database\Eloquent\Collection<int, Customer>, search: string, customerId: int}
     */
    protected function quotesIndexPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $customerId = (int) $request->query('customer_id', 0);

        $quotes = Quote::query()
            ->with('customer:id,first_name,last_name')
            ->when($customerId > 0, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where(function ($m) use ($search) {
                        $m->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $customers = Customer::query()->orderBy('first_name')->orderBy('last_name')->limit(500)->get(['id', 'first_name', 'last_name']);

        return [
            'quotes' => $quotes,
            'customers' => $customers,
            'search' => $search,
            'customerId' => $customerId,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $quote = Quote::query()->create([
            'customer_id' => (int) $validated['customer_id'],
            'status' => Quote::STATUS_DRAFT,
            'title' => $validated['title'] ?? null,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'subtotal_amount' => '0.00',
            'total_amount' => '0.00',
        ]);

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote created. Add lines below.');
    }

    public function show(Quote $quote): View|JsonResponse
    {
        return view('quotes.show', $this->quoteShowPayload($quote));
    }

    /**
     * @return array{quote: Quote, services: \Illuminate\Database\Eloquent\Collection<int, Service>, packages: \Illuminate\Database\Eloquent\Collection<int, TreatmentPackage>, linkableAppointments: \Illuminate\Database\Eloquent\Collection<int, Appointment>}
     */
    protected function quoteShowPayload(Quote $quote): array
    {
        $quote->load(['lines.service', 'lines.treatmentPackage', 'customer:id,first_name,last_name,email,phone']);

        $services = Service::query()->where('is_active', true)->orderBy('name')->get();
        $packages = TreatmentPackage::query()->where('is_active', true)->orderBy('name')->get();

        $linkableAppointments = Appointment::query()
            ->where('customer_id', $quote->customer_id)
            ->whereNotIn('status', ['cancelled'])
            ->orderByDesc('scheduled_at')
            ->limit(40)
            ->get(['id', 'scheduled_at', 'status', 'total_amount', 'quote_id']);

        return [
            'quote' => $quote,
            'services' => $services,
            'packages' => $packages,
            'linkableAppointments' => $linkableAppointments,
        ];
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $quote->update([
            'title' => $validated['title'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'discount_amount' => number_format((float) ($validated['discount_amount'] ?? 0), 2, '.', ''),
            'tax_amount' => number_format((float) ($validated['tax_amount'] ?? 0), 2, '.', ''),
            'notes' => $validated['notes'] ?? null,
        ]);

        QuoteTotalsService::recalculate($quote->fresh());

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote updated.');
    }

    public function updateStatus(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Quote::STATUS_DRAFT,
                Quote::STATUS_SENT,
                Quote::STATUS_ACCEPTED,
                Quote::STATUS_DECLINED,
                Quote::STATUS_EXPIRED,
            ])],
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === Quote::STATUS_ACCEPTED) {
            $updates['accepted_at'] = now();
        } else {
            $updates['accepted_at'] = null;
        }

        $quote->update($updates);

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote status updated.');
    }

    public function storeLine(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'line_kind' => ['required', Rule::in(['service', 'package', 'custom'])],
            'service_id' => ['nullable', 'required_if:line_kind,service', 'exists:services,id'],
            'treatment_package_id' => ['nullable', 'required_if:line_kind,package', 'exists:treatment_packages,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'required_if:line_kind,custom', 'numeric', 'min:0'],
        ]);

        $kind = $validated['line_kind'];
        $quantity = (int) $validated['quantity'];

        if ($kind === 'service') {
            $service = Service::query()->findOrFail((int) $validated['service_id']);
            $unit = (float) $service->price;
            $lineTotal = round($unit * $quantity, 2);
            $quote->lines()->create([
                'line_kind' => 'service',
                'service_id' => $service->id,
                'treatment_package_id' => null,
                'label' => $service->name,
                'quantity' => $quantity,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        } elseif ($kind === 'package') {
            $package = TreatmentPackage::query()->findOrFail((int) $validated['treatment_package_id']);
            $unit = (float) $package->package_price;
            $lineTotal = round($unit * $quantity, 2);
            $quote->lines()->create([
                'line_kind' => 'package',
                'service_id' => null,
                'treatment_package_id' => $package->id,
                'label' => $package->name,
                'quantity' => $quantity,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        } else {
            $label = trim((string) ($validated['label'] ?? ''));
            if ($label === '') {
                return redirect()
                    ->route('quotes.show', $quote)
                    ->with('error', 'Custom lines need a description.');
            }
            $unit = (float) $validated['unit_price'];
            $lineTotal = round($unit * $quantity, 2);
            $quote->lines()->create([
                'line_kind' => 'custom',
                'service_id' => null,
                'treatment_package_id' => null,
                'label' => $label,
                'quantity' => $quantity,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        }

        QuoteTotalsService::recalculate($quote->fresh());

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Line added.');
    }

    public function destroyLine(QuoteLine $quoteLine): RedirectResponse
    {
        $quote = $quoteLine->quote;
        $quoteLine->delete();
        QuoteTotalsService::recalculate($quote->fresh());

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Line removed.');
    }

    public function linkAppointment(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
        ]);

        $appointment = Appointment::query()->findOrFail((int) $validated['appointment_id']);
        if ((int) $appointment->customer_id !== (int) $quote->customer_id) {
            return redirect()
                ->route('quotes.show', $quote)
                ->with('error', 'That appointment belongs to a different customer.');
        }

        $appointment->update(['quote_id' => $quote->id]);

        return redirect()
            ->route('quotes.show', $quote)
            ->with('status', 'Quote linked to the appointment for reconciliation.');
    }
}
