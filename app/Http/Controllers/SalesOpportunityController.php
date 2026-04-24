<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\AppointmentFormLookupCache;
use App\Support\CustomerTimeline;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOpportunityController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        return view('sales.pipeline', $this->pipelineIndexPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function pipelineIndexPayload(Request $request): array
    {
        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId > 0 && ! Customer::query()->whereKey($customerId)->exists()) {
            $customerId = 0;
        }

        $query = Opportunity::query()
            ->with(['customer', 'owner:id,name'])
            ->when($customerId > 0, fn ($q) => $q->where('customer_id', $customerId))
            ->orderByRaw('CASE WHEN expected_close_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expected_close_date')
            ->orderByDesc('id');

        $all = $query->get();
        $byStage = $all->groupBy('stage');

        $openStages = Opportunity::pipelineStages();
        $closedStages = Opportunity::closedStages();

        $openPipelineValue = (float) $all->whereIn('stage', $openStages)->sum('amount');
        $closingNext30Days = (float) $all->whereIn('stage', $openStages)
            ->filter(fn (Opportunity $o) => $o->expected_close_date
                && $o->expected_close_date->between(Carbon::today(), Carbon::today()->addDays(30)))
            ->sum('amount');

        $countsOpen = [];
        foreach ($openStages as $stage) {
            $countsOpen[$stage] = $byStage->get($stage, collect())->count();
        }

        $filterCustomer = $customerId > 0 ? Customer::query()->find($customerId) : null;

        return [
            'title' => 'Sales pipeline · BeautiSkin CRM',
            'byStage' => $byStage,
            'openStages' => $openStages,
            'closedStages' => $closedStages,
            'stageLabels' => Opportunity::stageLabels(),
            'customers' => AppointmentFormLookupCache::customers(),
            'staffUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'openPipelineValue' => $openPipelineValue,
            'closingNext30Days' => $closingNext30Days,
            'countsOpen' => $countsOpen,
            'customerIdFilter' => $customerId,
            'filterCustomer' => $filterCustomer,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'stage' => ['nullable', 'string', Rule::in(array_merge(Opportunity::pipelineStages(), Opportunity::closedStages()))],
        ]);

        $stage = $validated['stage'] ?? Opportunity::STAGE_NEW;
        $payload = [
            'customer_id' => (int) $validated['customer_id'],
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'title' => $validated['title'],
            'stage' => $stage,
            'amount' => $validated['amount'] ?? 0,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'loss_reason' => null,
        ];
        $payload = array_merge($payload, $this->timestampsForStage($stage, null));

        $created = Opportunity::create($payload);
        $customer = Customer::query()->find($created->customer_id);
        if ($customer) {
            $labels = Opportunity::stageLabels();
            $stageLabel = $labels[$created->stage] ?? $created->stage;
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_OPPORTUNITY_CREATED,
                sprintf('Opportunity: %s · %s · $%s', $created->title, $stageLabel, number_format((float) $created->amount, 2)),
                $request->user()->id,
                null,
                CustomerActivity::CATEGORY_SALES,
                ['opportunity_id' => $created->id],
            );
        }

        return $this->redirectToPipeline($request, 'Opportunity created.');
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'stage' => ['required', 'string', Rule::in(array_merge(Opportunity::pipelineStages(), Opportunity::closedStages()))],
            'loss_reason' => [
                Rule::requiredIf($request->input('stage') === Opportunity::STAGE_LOST),
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $newStage = $validated['stage'];
        $previousStage = $opportunity->stage;
        $payload = [
            'customer_id' => (int) $validated['customer_id'],
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'title' => $validated['title'],
            'stage' => $newStage,
            'amount' => $validated['amount'] ?? 0,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($newStage === Opportunity::STAGE_LOST) {
            $payload['loss_reason'] = (string) ($validated['loss_reason'] ?? '');
        } else {
            $payload['loss_reason'] = null;
        }

        $payload = array_merge($payload, $this->timestampsForStage($newStage, $opportunity));

        $opportunity->update($payload);

        $customer = Customer::query()->find((int) $validated['customer_id']);
        if ($customer) {
            $labels = Opportunity::stageLabels();
            if ($previousStage !== $newStage) {
                $from = $labels[$previousStage] ?? $previousStage;
                $to = $labels[$newStage] ?? $newStage;
                $summary = sprintf('Opportunity stage: %s → %s · %s', $from, $to, $opportunity->title);
                if ($newStage === Opportunity::STAGE_LOST && ($payload['loss_reason'] ?? '') !== '') {
                    $summary .= ' · '.Str::limit((string) $payload['loss_reason'], 100);
                }
                CustomerTimeline::record(
                    $customer,
                    CustomerActivity::EVENT_OPPORTUNITY_STAGE_CHANGED,
                    $summary,
                    $request->user()->id,
                    null,
                    CustomerActivity::CATEGORY_SALES,
                    ['opportunity_id' => $opportunity->id],
                );
            }
        }

        return $this->redirectToPipeline($request, 'Opportunity updated.');
    }

    public function updateStage(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::in(array_merge(Opportunity::pipelineStages(), Opportunity::closedStages()))],
            'loss_reason' => [
                Rule::requiredIf($request->input('stage') === Opportunity::STAGE_LOST),
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $newStage = $validated['stage'];
        $previousStage = $opportunity->stage;
        $payload = [
            'stage' => $newStage,
            'loss_reason' => $newStage === Opportunity::STAGE_LOST
                ? (string) ($validated['loss_reason'] ?? '')
                : null,
        ];
        $payload = array_merge($payload, $this->timestampsForStage($newStage, $opportunity));

        $opportunity->update($payload);

        $customer = Customer::query()->find($opportunity->customer_id);
        if ($customer && $previousStage !== $newStage) {
            $labels = Opportunity::stageLabels();
            $from = $labels[$previousStage] ?? $previousStage;
            $to = $labels[$newStage] ?? $newStage;
            $summary = sprintf('Opportunity stage: %s → %s · %s', $from, $to, $opportunity->title);
            if ($newStage === Opportunity::STAGE_LOST && ($payload['loss_reason'] ?? '') !== '') {
                $summary .= ' · '.Str::limit((string) $payload['loss_reason'], 100);
            }
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_OPPORTUNITY_STAGE_CHANGED,
                $summary,
                $request->user()->id,
                null,
                CustomerActivity::CATEGORY_SALES,
                ['opportunity_id' => $opportunity->id],
            );
        }

        return $this->redirectToPipeline($request, 'Stage updated.');
    }

    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $customer = Customer::query()->find($opportunity->customer_id);
        $title = $opportunity->title;
        $oppId = $opportunity->id;
        if ($customer) {
            CustomerTimeline::record(
                $customer,
                CustomerActivity::EVENT_OPPORTUNITY_REMOVED,
                sprintf('Opportunity removed: %s', $title),
                $request->user()->id,
                null,
                CustomerActivity::CATEGORY_SALES,
                ['opportunity_id' => $oppId],
            );
        }

        $opportunity->delete();

        return $this->redirectToPipeline($request, 'Opportunity removed.');
    }

    private function redirectToPipeline(Request $request, string $message): RedirectResponse
    {
        $cid = (int) ($request->input('pipeline_customer_id') ?: 0);

        $query = [];
        if ($cid > 0 && Customer::query()->whereKey($cid)->exists()) {
            $query['customer_id'] = $cid;
        }

        return redirect()
            ->route('sales.pipeline.index', $query)
            ->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function timestampsForStage(string $newStage, ?Opportunity $existing = null): array
    {
        if ($newStage === Opportunity::STAGE_WON) {
            $keep = $existing && $existing->stage === Opportunity::STAGE_WON;

            return [
                'won_at' => $keep ? $existing->won_at : now(),
                'lost_at' => null,
            ];
        }

        if ($newStage === Opportunity::STAGE_LOST) {
            $keep = $existing && $existing->stage === Opportunity::STAGE_LOST;

            return [
                'won_at' => null,
                'lost_at' => $keep ? $existing->lost_at : now(),
            ];
        }

        return [
            'won_at' => null,
            'lost_at' => null,
        ];
    }
}

