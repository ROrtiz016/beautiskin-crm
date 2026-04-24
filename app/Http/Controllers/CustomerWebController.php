<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Service;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\AppointmentCancellation;
use App\Support\AppointmentLedger;
use App\Services\InventoryStockService;
use App\Support\AppointmentFormLookupCache;
use App\Support\CustomerGeo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerWebController extends Controller
{
    private const CUSTOMER_PROFILE_APPOINTMENTS_LIMIT = 250;

    private const CUSTOMER_PAYMENT_HISTORY_LIMIT = 120;

    public function index(Request $request): View|JsonResponse
    {
        return view('customers.index', $this->customersIndexPayload($request));
    }

    /**
     * @return array{customers: \Illuminate\Contracts\Pagination\LengthAwarePaginator, search: string, sort: string, direction: string}
     */
    protected function customersIndexPayload(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'created_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableColumns = [
            'name' => ['first_name', 'last_name'],
            'email' => ['email'],
            'phone' => ['phone'],
            'date_of_birth' => ['date_of_birth'],
            'appointments_count' => ['appointments_count'],
            'created_at' => ['created_at'],
        ];

        if (! array_key_exists($sort, $sortableColumns)) {
            $sort = 'created_at';
        }

        $customers = Customer::query()
            ->withCount('appointments')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->tap(function ($query) use ($sort, $direction, $sortableColumns) {
                foreach ($sortableColumns[$sort] as $column) {
                    $query->orderBy($column, $direction);
                }
            })
            ->paginate(10)
            ->withQueryString();

        return [
            'customers' => $customers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        if (array_key_exists('country', $validated)) {
            $validated['country'] = CustomerGeo::normalizeCountry($validated['country'] ?? null);
        }

        Customer::create($validated);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer created successfully.');
    }

    public function edit(Customer $customer): View|JsonResponse
    {
        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        if (array_key_exists('country', $validated)) {
            $validated['country'] = CustomerGeo::normalizeCountry($validated['country'] ?? null);
        }

        $customer->update($validated);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer updated successfully.');
    }

    public function updateContactDetails(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
        ]);

        $customer->update($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Contact details updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer deleted successfully.');
    }

    public function show(Customer $customer): View|JsonResponse
    {
        return view('customers.show', $this->customerShowPayload($customer));
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerShowPayload(Customer $customer): array
    {
        $customer->load([
            'appointments' => function ($query) {
                $query->withSum('paymentEntries', 'amount')
                    ->with(['services', 'staffUser', 'cancelledBy:id,name', 'quote:id,title,status,total_amount', 'paymentEntries' => function ($q) {
                        $q->orderByDesc('created_at')->limit(6);
                    }])
                    ->latest('scheduled_at')
                    ->limit(self::CUSTOMER_PROFILE_APPOINTMENTS_LIMIT);
            },
            'opportunities' => function ($query) {
                $query->select([
                    'id',
                    'customer_id',
                    'owner_user_id',
                    'title',
                    'stage',
                    'amount',
                    'expected_close_date',
                    'updated_at',
                ])
                    ->with(['owner:id,name'])
                    ->orderByDesc('updated_at')
                    ->limit(25);
            },
            'tasks' => function ($query) {
                $query->select([
                    'id',
                    'customer_id',
                    'opportunity_id',
                    'assigned_to_user_id',
                    'title',
                    'status',
                    'kind',
                    'due_at',
                ])
                    ->with(['assignedTo:id,name', 'opportunity:id,title'])
                    ->orderByRaw("CASE WHEN tasks.status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('tasks.due_at')
                    ->limit(40);
            },
            'activities' => function ($query) {
                $query->select([
                    'id',
                    'customer_id',
                    'user_id',
                    'event_type',
                    'category',
                    'summary',
                    'created_at',
                ])
                    ->with(['user:id,name'])
                    ->latest('created_at')
                    ->limit(50);
            },
            'memberships.membership',
        ]);

        $totalSpent = (float) Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('total_amount');

        $paymentHistory = Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->latest('scheduled_at')
            ->limit(self::CUSTOMER_PAYMENT_HISTORY_LIMIT)
            ->get(['id', 'scheduled_at', 'status', 'total_amount', 'notes']);

        $servicesReceived = AppointmentService::query()
            ->selectRaw('service_name, SUM(quantity) as total_quantity, SUM(line_total) as total_spent, COUNT(DISTINCT appointment_id) as visits')
            ->join('appointments', 'appointments.id', '=', 'appointment_services.appointment_id')
            ->where('appointments.customer_id', $customer->id)
            ->groupBy('service_name')
            ->orderByDesc('visits')
            ->get();

        $currentMemberships = $customer->memberships
            ->filter(function ($membership) {
                return $membership->status === 'active' && (! $membership->end_date || $membership->end_date->isFuture());
            })
            ->values();

        $pastMemberships = $customer->memberships
            ->filter(function ($membership) {
                return $membership->status !== 'active' || ($membership->end_date && $membership->end_date->isPast());
            })
            ->values();

        $now = Carbon::now();

        $nextAppointment = $customer->appointments
            ->filter(function ($appointment) use ($now) {
                return $appointment->scheduled_at && $appointment->scheduled_at->greaterThanOrEqualTo($now) && $appointment->status === 'booked';
            })
            ->sortBy('scheduled_at')
            ->first();

        $bookedAppointments = $customer->appointments
            ->filter(function ($appointment) use ($now) {
                return $appointment->scheduled_at && $appointment->scheduled_at->greaterThanOrEqualTo($now) && $appointment->status === 'booked';
            })
            ->sortBy('scheduled_at')
            ->values();

        $pastAppointments = $customer->appointments
            ->filter(function ($appointment) use ($now) {
                return ($appointment->scheduled_at && $appointment->scheduled_at->lessThan($now))
                    || in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true);
            })
            ->sortByDesc('scheduled_at')
            ->values();

        $recentlyChangedAppointmentIds = $customer->appointments
            ->filter(function ($appointment) use ($now) {
                return in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true)
                    && $appointment->updated_at
                    && $appointment->updated_at->greaterThanOrEqualTo($now->copy()->subHours(24));
            })
            ->pluck('id')
            ->all();

        $retailSaleServices = Service::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $keys = Service::retailCategoryKeys();
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $query
                    ->whereRaw('LOWER(TRIM(COALESCE(category, ""))) IN ('.$placeholders.')', $keys)
                    ->orWhere('track_inventory', true);
            })
            ->orderBy('name')
            ->get();

        return [
            'customer' => $customer,
            'paymentHistory' => $paymentHistory,
            'servicesReceived' => $servicesReceived,
            'currentMemberships' => $currentMemberships,
            'pastMemberships' => $pastMemberships,
            'totalSpent' => $totalSpent,
            'paymentHistoryDisplayLimit' => self::CUSTOMER_PAYMENT_HISTORY_LIMIT,
            'appointmentsProfileDisplayLimit' => self::CUSTOMER_PROFILE_APPOINTMENTS_LIMIT,
            'nextAppointment' => $nextAppointment,
            'bookedAppointments' => $bookedAppointments,
            'pastAppointments' => $pastAppointments,
            'recentlyChangedAppointmentIds' => $recentlyChangedAppointmentIds,
            'services' => AppointmentFormLookupCache::activeServices(),
            'retailSaleServices' => $retailSaleServices,
            'staffUsers' => AppointmentFormLookupCache::staffUsers(),
            'clinicSettings' => ClinicSetting::current(),
        ];
    }

    public function storeAppointment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'quote_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('quotes', 'id')->where(fn ($q) => $q->where('customer_id', $customer->id)),
            ],
            'services' => ['array'],
            'services.*.service_id' => ['nullable', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ], AppointmentPolicyEnforcer::depositRulesForRequest()));

        $dateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($dateKey);

        $depositPaid = AppointmentPolicyEnforcer::depositPaidFromValidated($validated);
        $depositAmount = $depositPaid ? AppointmentPolicyEnforcer::defaultDepositAmount() : null;

        $appointment = Appointment::query()->create([
            'customer_id' => $customer->id,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'quote_id' => $validated['quote_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'booked',
            'arrived_confirmed' => false,
            'notes' => $validated['notes'] ?? null,
            'total_amount' => number_format(0, 2, '.', ''),
            'deposit_amount' => $depositAmount,
            'deposit_paid' => $depositPaid,
        ]);

        $this->syncAppointmentServices($appointment, $validated['services'] ?? []);

        AppointmentLedger::recordBookingDepositIfPaid($appointment, $depositPaid, $depositAmount, $request->user()?->id);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Appointment booked successfully.');
    }

    public function updateAppointmentStatus(Request $request, Customer $customer, Appointment $appointment): RedirectResponse
    {
        if ($appointment->customer_id !== $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['booked', 'completed', 'cancelled'])],
        ]);

        $appointment->update(
            AppointmentCancellation::attributesWhenChangingStatus($request, $appointment, $validated['status'])
        );

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Appointment marked as ' . $validated['status'] . '.');
    }

    public function updateAppointment(Request $request, Customer $customer, Appointment $appointment): RedirectResponse
    {
        if ($appointment->customer_id !== $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'services' => ['array'],
            'services.*.service_id' => ['nullable', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $newDateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        $oldDateKey = AppointmentPolicyEnforcer::appointmentDateKey($appointment->scheduled_at);
        if ($newDateKey !== $oldDateKey) {
            AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($newDateKey, $appointment->id);
        }

        $appointment->update([
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($appointment->status !== 'completed') {
            $this->syncAppointmentServices($appointment, $validated['services'] ?? []);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Appointment updated successfully.');
    }

    public function storeAppointmentRetailLine(Request $request, Customer $customer, Appointment $appointment): RedirectResponse
    {
        if ($appointment->customer_id !== $customer->id) {
            abort(404);
        }

        if ($appointment->status !== 'completed') {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', 'Retail items can only be added to completed visits.');
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $service = Service::query()
            ->whereKey($validated['service_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $service->eligibleForRetailSaleOnVisit()) {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', 'That catalog item cannot be sold as retail on a completed visit.');
        }

        $quantity = (int) $validated['quantity'];

        if ($service->track_inventory && (int) $service->stock_quantity < $quantity) {
            return redirect()
                ->route('customers.show', $customer)
                ->with('error', 'Insufficient stock for '.$service->name.'.');
        }

        try {
            DB::transaction(function () use ($appointment, $service, $quantity) {
                $locked = Service::query()->whereKey($service->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->is_active) {
                    throw ValidationException::withMessages([
                        'service_id' => 'That item is no longer available.',
                    ]);
                }

                if ($locked->track_inventory && (int) $locked->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Insufficient stock for '.$locked->name.'.',
                    ]);
                }

                $unit = (float) $locked->price;
                $lineTotal = round($unit * $quantity, 2);

                $appointment->services()->create([
                    'service_id' => $locked->id,
                    'service_name' => $locked->name,
                    'duration_minutes' => (int) $locked->duration_minutes,
                    'quantity' => $quantity,
                    'unit_price' => number_format($unit, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ]);

                InventoryStockService::deductForService($locked, $quantity);

                $appointment->refresh();
                $sum = round((float) $appointment->services()->sum('line_total'), 2);
                $appointment->update([
                    'total_amount' => number_format($sum, 2, '.', ''),
                ]);
            });
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Unable to add retail line.';

            return redirect()
                ->route('customers.show', $customer)
                ->withErrors($e->errors())
                ->with('error', $message);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Retail line added to the visit.');
    }

    private function syncAppointmentServices(Appointment $appointment, array $serviceLines): void
    {
        $appointment->services()->delete();
        $total = 0.0;

        foreach ($serviceLines as $line) {
            if (empty($line['service_id'])) {
                continue;
            }

            $service = Service::query()->findOrFail((int) $line['service_id']);
            $quantity = (int) ($line['quantity'] ?? 1);
            $lineTotal = round(((float) $service->price) * $quantity, 2);
            $total = round($total + $lineTotal, 2);

            $appointment->services()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'quantity' => $quantity,
                'unit_price' => $service->price,
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);
        }

        $appointment->update([
            'total_amount' => number_format($total, 2, '.', ''),
        ]);
    }
}
