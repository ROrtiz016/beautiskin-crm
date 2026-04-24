<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\LeadFunnelMetrics;
use App\Support\LeadSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeadsController extends Controller
{
    private const STATUSES = ['waiting', 'contacted', 'booked', 'cancelled'];

    public function index(Request $request): View
    {
        return view('leads.index', $this->leadsIndexPayload($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function leadsIndexPayload(Request $request): array
    {
        $filters = $this->parseLeadListFilters($request);

        $listQuery = WaitlistEntry::query()
            ->with([
                'customer:id,first_name,last_name,email,phone',
                'service:id,name',
                'staffUser:id,name,email',
                'contactedBy:id,name',
            ])
            ->orderByDesc('preferred_date')
            ->orderByDesc('id');
        $this->applyLeadListFilters($listQuery, $filters);
        $entries = $listQuery->paginate(20)->withQueryString();

        $chartQuery = WaitlistEntry::query();
        $this->applyLeadListFilters($chartQuery, $filters);
        $countsBySource = $chartQuery
            ->select('lead_source', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('lead_source')
            ->pluck('aggregate', 'lead_source');

        $leadSourceChart = $this->buildLeadSourceChart($countsBySource);

        $countsByStatus = WaitlistEntry::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $serviceOptions = Service::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $staffOptions = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $hasActiveFilters = $filters['search'] !== '' || $filters['status'] !== '' || $filters['preferredFrom'] !== ''
            || $filters['preferredTo'] !== '' || $filters['serviceId'] > 0 || $filters['assignedTo'] !== ''
            || $filters['createdFrom'] !== '';

        return array_merge(LeadFunnelMetrics::snapshot(), [
            'title' => 'Leads · BeautiSkin CRM',
            'entries' => $entries,
            'statusFilter' => $filters['status'],
            'search' => $filters['search'],
            'preferredFrom' => $filters['preferredFrom'],
            'preferredTo' => $filters['preferredTo'],
            'serviceIdFilter' => $filters['serviceId'],
            'assignedToFilter' => $filters['assignedTo'],
            'createdFrom' => $filters['createdFrom'],
            'countsByStatus' => $countsByStatus,
            'statusLabels' => self::STATUSES,
            'serviceOptions' => $serviceOptions,
            'staffOptions' => $staffOptions,
            'hasActiveFilters' => $hasActiveFilters,
            'leadFunnelHideNav' => true,
            'leadSourceChart' => $leadSourceChart,
        ]);
    }

    /**
     * @return array{
     *     search: string,
     *     status: string,
     *     preferredFrom: string,
     *     preferredTo: string,
     *     serviceId: int,
     *     assignedTo: string,
     *     createdFrom: string
     * }
     */
    protected function parseLeadListFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'preferred_from' => ['nullable', 'date'],
            'preferred_to' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'string', 'max:32'],
            'created_from' => ['nullable', 'date'],
        ]);

        $status = (string) $request->query('status', '');
        if ($status !== '' && ! in_array($status, self::STATUSES, true)) {
            $status = '';
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $preferredFrom = isset($validated['preferred_from']) ? (string) $validated['preferred_from'] : '';
        $preferredTo = isset($validated['preferred_to']) ? (string) $validated['preferred_to'] : '';
        if ($preferredFrom !== '' && $preferredTo !== '' && $preferredFrom > $preferredTo) {
            [$preferredFrom, $preferredTo] = [$preferredTo, $preferredFrom];
        }

        $serviceId = (int) $request->query('service_id', 0);
        if ($serviceId > 0 && ! Service::query()->whereKey($serviceId)->exists()) {
            $serviceId = 0;
        }

        $assignedTo = trim((string) ($validated['assigned_to'] ?? ''));
        if ($assignedTo !== 'none') {
            if ($assignedTo === '' || ! ctype_digit($assignedTo) || (int) $assignedTo < 1) {
                $assignedTo = '';
            } elseif (! User::query()->whereKey((int) $assignedTo)->exists()) {
                $assignedTo = '';
            }
        }

        $createdFrom = isset($validated['created_from']) ? (string) $validated['created_from'] : '';

        return [
            'search' => $search,
            'status' => $status,
            'preferredFrom' => $preferredFrom,
            'preferredTo' => $preferredTo,
            'serviceId' => $serviceId,
            'assignedTo' => $assignedTo,
            'createdFrom' => $createdFrom,
        ];
    }

    /**
     * @param  array{search: string, status: string, preferredFrom: string, preferredTo: string, serviceId: int, assignedTo: string, createdFrom: string}  $f
     */
    protected function applyLeadListFilters(Builder $query, array $f): void
    {
        if ($f['status'] !== '') {
            $query->where('status', $f['status']);
        }

        if ($f['search'] !== '') {
            $search = $f['search'];
            $query->whereHas('customer', function ($customerQuery) use ($search): void {
                $customerQuery
                    ->where(function ($q) use ($search): void {
                        $q->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($f['preferredFrom'] !== '') {
            $query->whereDate('preferred_date', '>=', $f['preferredFrom']);
        }
        if ($f['preferredTo'] !== '') {
            $query->whereDate('preferred_date', '<=', $f['preferredTo']);
        }

        if ($f['serviceId'] > 0) {
            $query->where('service_id', $f['serviceId']);
        }

        if ($f['assignedTo'] === 'none') {
            $query->whereNull('staff_user_id');
        } elseif ($f['assignedTo'] !== '' && ctype_digit($f['assignedTo']) && (int) $f['assignedTo'] > 0) {
            $query->where('staff_user_id', (int) $f['assignedTo']);
        }

        if ($f['createdFrom'] !== '') {
            $query->where('created_at', '>=', Carbon::parse($f['createdFrom'])->startOfDay());
        }
    }

    /**
     * @param  Collection<string, int|string>  $countsBySource
     * @return array{items: list<array{key: string, label: string, count: int, percent: float}>, total: int, hasData: bool}
     */
    protected function buildLeadSourceChart(Collection $countsBySource): array
    {
        $total = (int) $countsBySource->sum();
        if ($total === 0) {
            return ['items' => [], 'total' => 0, 'hasData' => false];
        }

        $palette = [
            '#ec4899', '#8b5cf6', '#0ea5e9', '#14b8a6', '#f97316',
            '#eab308', '#64748b', '#22c55e', '#a855f7', '#ef4444',
            '#06b6d4', '#84cc16', '#f43f5e', '#6366f1', '#78716c',
        ];

        $items = [];
        $i = 0;
        foreach ($countsBySource->sortDesc() as $src => $cnt) {
            $n = (int) $cnt;
            if ($n < 1) {
                continue;
            }
            $key = (string) $src;
            $items[] = [
                'key' => $key,
                'label' => LeadSource::label($key),
                'count' => $n,
                'percent' => round(100 * $n / $total, 1),
                'color' => $palette[$i % count($palette)],
            ];
            $i++;
        }

        return ['items' => $items, 'total' => $total, 'hasData' => $items !== []];
    }
}
