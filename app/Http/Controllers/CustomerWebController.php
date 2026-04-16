<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerWebController extends Controller
{
    public function index(Request $request): View
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

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
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
            'notes' => ['nullable', 'string'],
        ]);

        Customer::create($validated);

        return redirect()
            ->route('customers.index')
            ->with('status', 'Customer created successfully.');
    }

    public function edit(Customer $customer): View
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
            'notes' => ['nullable', 'string'],
        ]);

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

    public function show(Customer $customer): View
    {
        $customer->load([
            'appointments' => function ($query) {
                $query->with(['services', 'staffUser'])->latest('scheduled_at');
            },
            'memberships.membership',
        ]);

        $paymentHistory = $customer->appointments()
            ->where('status', 'completed')
            ->latest('scheduled_at')
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

        return view('customers.show', [
            'customer' => $customer,
            'paymentHistory' => $paymentHistory,
            'servicesReceived' => $servicesReceived,
            'currentMemberships' => $currentMemberships,
            'pastMemberships' => $pastMemberships,
            'totalSpent' => $paymentHistory->sum('total_amount'),
            'nextAppointment' => $nextAppointment,
            'bookedAppointments' => $bookedAppointments,
            'pastAppointments' => $pastAppointments,
            'recentlyChangedAppointmentIds' => $recentlyChangedAppointmentIds,
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']),
            'staffUsers' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeAppointment(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'services' => ['array'],
            'services.*.service_id' => ['nullable', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $appointment = Appointment::query()->create([
            'customer_id' => $customer->id,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'booked',
            'arrived_confirmed' => false,
            'notes' => $validated['notes'] ?? null,
            'total_amount' => number_format(0, 2, '.', ''),
        ]);

        $this->syncAppointmentServices($appointment, $validated['services'] ?? []);

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

        $appointment->update([
            'status' => $validated['status'],
        ]);

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

        $appointment->update([
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->syncAppointmentServices($appointment, $validated['services'] ?? []);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', 'Appointment updated successfully.');
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
