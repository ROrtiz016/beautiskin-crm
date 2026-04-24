<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Service;
use App\Models\TreatmentPackage;
use App\Services\QuoteTotalsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function quoteShowArray(Quote $quote): array
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

    public function store(Request $request): JsonResponse
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

        return response()->json($this->quoteShowArray($quote->fresh()), 201);
    }

    public function update(Request $request, Quote $quote): JsonResponse
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

        return response()->json($this->quoteShowArray($quote->fresh()));
    }

    public function updateStatus(Request $request, Quote $quote): JsonResponse
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

        return response()->json($this->quoteShowArray($quote->fresh()));
    }

    public function storeLine(Request $request, Quote $quote): JsonResponse
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
                return response()->json([
                    'message' => 'Custom lines need a description.',
                ], 422);
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

        return response()->json($this->quoteShowArray($quote->fresh()), 201);
    }

    public function destroyLine(QuoteLine $quoteLine): JsonResponse
    {
        $quote = $quoteLine->quote;
        $quoteLine->delete();
        QuoteTotalsService::recalculate($quote->fresh());

        return response()->json($this->quoteShowArray($quote->fresh()));
    }

    public function linkAppointment(Request $request, Quote $quote): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id'],
        ]);

        $appointment = Appointment::query()->findOrFail((int) $validated['appointment_id']);
        if ((int) $appointment->customer_id !== (int) $quote->customer_id) {
            return response()->json([
                'message' => 'That appointment belongs to a different customer.',
            ], 422);
        }

        $appointment->update(['quote_id' => $quote->id]);

        return response()->json($this->quoteShowArray($quote->fresh()));
    }
}
