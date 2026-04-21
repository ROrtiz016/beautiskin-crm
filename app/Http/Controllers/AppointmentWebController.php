<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\CommunicationLog;
use App\Models\Service;
use App\Models\WaitlistEntry;
use App\Notifications\AppointmentReminderNotification;
use App\Services\CommunicationRecorder;
use App\Services\CustomerMessagingTemplateService;
use App\Services\AppointmentPolicyEnforcer;
use App\Support\AppointmentCancellation;
use App\Support\AppointmentFormLookupCache;
use App\Support\ContactMethod;
use App\Support\LeadSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentWebController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $hasExplicitDate = $request->query->has('date');
        $selectedDate = $this->parseDate((string) $request->query('date', $today->toDateString()), $today);
        $monthBase = $this->parseMonth((string) $request->query('month', $selectedDate->format('Y-m')), $selectedDate);
        $staffUsers = AppointmentFormLookupCache::staffUsers();

        $monthStart = $monthBase->copy()->startOfMonth();
        $monthEnd = $monthBase->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);

        $filteredQuery = $this->appointmentsFilteredQuery($request);

        $calendarAppointments = (clone $filteredQuery)
            ->whereBetween('scheduled_at', [$calendarStart, $calendarEnd])
            ->orderBy('scheduled_at')
            ->get(['id', 'scheduled_at']);

        $appointmentsByDate = $calendarAppointments->groupBy(
            fn (Appointment $appointment) => optional($appointment->scheduled_at)->toDateString()
        );

        if (! $hasExplicitDate && $appointmentsByDate->isNotEmpty() && ! $appointmentsByDate->has($selectedDate->toDateString())) {
            $selectedDate = $this->defaultSelectedDate($appointmentsByDate, $today, $monthBase);
        }

        $selectedAppointments = (clone $this->appointmentsBaseQuery($request))
            ->whereDate('scheduled_at', $selectedDate->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        $selectedWaitlistEntries = $this->waitlistEntriesForDate($selectedDate);

        $todayStr = $today->toDateString();
        if ($today->between($calendarStart->copy()->startOfDay(), $calendarEnd->copy()->endOfDay())) {
            $todaysAppointments = $selectedDate->isSameDay($today)
                ? $selectedAppointments
                : (clone $this->appointmentsBaseQuery($request))
                    ->whereDate('scheduled_at', $today)
                    ->orderBy('scheduled_at')
                    ->get();
        } else {
            $todaysAppointments = (clone $this->appointmentsBaseQuery($request))
                ->whereDate('scheduled_at', $today)
                ->orderBy('scheduled_at')
                ->get();
        }

        $weeks = [];
        $cursor = $calendarStart->copy();
        while ($cursor->lte($calendarEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $monthBase->month,
                    'is_selected' => $dateKey === $selectedDate->toDateString(),
                    'is_today' => $dateKey === $today->toDateString(),
                    'count' => $appointmentsByDate->get($dateKey, collect())->count(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('appointments.index', [
            'today' => $today,
            'selectedDate' => $selectedDate,
            'monthBase' => $monthBase,
            'weeks' => $weeks,
            'todaysAppointments' => $todaysAppointments,
            'selectedAppointments' => $selectedAppointments,
            'selectedWaitlistEntries' => $selectedWaitlistEntries,
            'staffAvailability' => $this->buildStaffAvailability($selectedAppointments, $staffUsers),
            'customers' => AppointmentFormLookupCache::customers(),
            'services' => AppointmentFormLookupCache::activeServices(),
            'staffUsers' => $staffUsers,
            'filters' => $this->filterParamsFromRequest($request),
            'clinicSettings' => ClinicSetting::current(),
            'leadSourceOptions' => LeadSource::selectOptions(),
        ]);
    }

    public function dayFragment(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $selectedDate = $this->parseDate((string) $request->query('date', $today->toDateString()), $today);
        $monthBase = $this->parseMonth((string) $request->query('month', $selectedDate->format('Y-m')), $selectedDate);
        $staffUsers = AppointmentFormLookupCache::staffUsers();
        $selectedAppointments = (clone $this->appointmentsBaseQuery($request))
            ->whereDate('scheduled_at', $selectedDate->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        return response()->json([
            'html' => view('appointments.partials.selected-day-panel', [
                'selectedAppointments' => $selectedAppointments,
                'selectedDate' => $selectedDate,
                'selectedWaitlistEntries' => $this->waitlistEntriesForDate($selectedDate),
                'staffAvailability' => $this->buildStaffAvailability($selectedAppointments, $staffUsers),
            ])->render(),
            'date' => $selectedDate->toDateString(),
            'month' => $monthBase->format('Y-m'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAppointmentPayload($request);
        $this->ensureNoStaffConflict($validated);

        $dateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($dateKey);

        $depositPaid = AppointmentPolicyEnforcer::depositPaidFromValidated($validated);
        $depositAmount = $depositPaid ? AppointmentPolicyEnforcer::defaultDepositAmount() : null;

        $appointment = Appointment::query()->create([
            'customer_id' => $validated['customer_id'],
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'booked',
            'arrived_confirmed' => false,
            'notes' => $validated['notes'] ?? null,
            'total_amount' => number_format(0, 2, '.', ''),
            'deposit_amount' => $depositAmount,
            'deposit_paid' => $depositPaid,
        ]);

        $this->syncAppointmentServices($appointment, $validated['services']);

        return redirect()
            ->route('appointments.index', [
                'month' => Carbon::parse($validated['scheduled_at'])->format('Y-m'),
                'date' => Carbon::parse($validated['scheduled_at'])->toDateString(),
            ])
            ->with('status', 'Appointment created successfully.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $this->validateAppointmentPayload($request);
        $this->ensureNoStaffConflict($validated, $appointment);

        $newDateKey = AppointmentPolicyEnforcer::appointmentDateKey($validated['scheduled_at']);
        $oldDateKey = AppointmentPolicyEnforcer::appointmentDateKey($appointment->scheduled_at);
        if ($newDateKey !== $oldDateKey) {
            AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded($newDateKey, $appointment->id);
        }

        $appointment->update([
            'customer_id' => $validated['customer_id'],
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($appointment->status !== 'completed') {
            $this->syncAppointmentServices($appointment, $validated['services']);
        }

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Appointment updated successfully.');
    }

    public function reschedule(Request $request, Appointment $appointment): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'target_date' => ['required', 'date'],
        ]);

        $targetDate = Carbon::parse($validated['target_date']);
        $scheduledAt = $appointment->scheduled_at
            ? $appointment->scheduled_at->copy()->setDate($targetDate->year, $targetDate->month, $targetDate->day)
            : $targetDate->copy()->setTime(9, 0);
        $endsAt = $appointment->ends_at
            ? $appointment->ends_at->copy()->setDate($targetDate->year, $targetDate->month, $targetDate->day)
            : null;

        $payload = [
            'customer_id' => $appointment->customer_id,
            'staff_user_id' => $appointment->staff_user_id,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'services' => $appointment->services->map(fn ($service) => [
                'service_id' => $service->service_id,
                'quantity' => $service->quantity,
            ])->values()->all(),
        ];

        $this->ensureNoStaffConflict($payload, $appointment);

        AppointmentPolicyEnforcer::assertMaxBookingsNotExceeded(
            AppointmentPolicyEnforcer::appointmentDateKey($payload['scheduled_at']),
            $appointment->id
        );

        $appointment->update([
            'scheduled_at' => $payload['scheduled_at'],
            'ends_at' => $payload['ends_at'],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'ok',
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ]);
        }

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Appointment rescheduled successfully.');
    }

    public function sendEmailReminder(Request $request, Appointment $appointment): RedirectResponse
    {
        $appointment->loadMissing(['customer', 'services', 'staffUser']);

        if (! $appointment->customer?->email) {
            return redirect()
                ->route('appointments.index', [
                    'month' => optional($appointment->scheduled_at)->format('Y-m'),
                    'date' => optional($appointment->scheduled_at)->toDateString(),
                ])
                ->with('error', 'This customer does not have an email address on file.');
        }

        $appointment->customer->notify(new AppointmentReminderNotification($appointment));

        $settings = ClinicSetting::current();
        $rendered = CustomerMessagingTemplateService::render('reminder', $appointment->customer, $appointment, $settings);
        $from = $settings->email_from_address ? (string) $settings->email_from_address : (string) config('mail.from.address');

        CommunicationRecorder::recordStructured(
            $appointment->customer,
            CommunicationLog::CHANNEL_EMAIL,
            CommunicationLog::DIRECTION_OUTBOUND,
            CommunicationLog::PROVIDER_CRM,
            null,
            'reminder',
            (string) ($rendered['subject'] ?? 'Appointment reminder'),
            (string) ($rendered['email_body'] ?? ''),
            $from,
            (string) $appointment->customer->email,
            'sent',
            (int) $request->user()->id,
            $appointment->id,
            ['trigger' => 'appointment_reminder_email'],
        );

        $appointment->update([
            'email_reminder_sent_at' => now(),
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Appointment reminder email sent.');
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['booked', 'completed', 'cancelled', 'no_show'])],
        ]);

        $appointment->update(
            AppointmentCancellation::attributesWhenChangingStatus($request, $appointment, $validated['status'])
        );

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Appointment marked as '.str_replace('_', ' ', $validated['status']).'.');
    }

    public function updateArrival(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'arrived_confirmed' => ['required', 'boolean'],
        ]);

        $appointment->update([
            'arrived_confirmed' => (bool) $validated['arrived_confirmed'],
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Arrival confirmation updated.');
    }

    public function updateStaff(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'staff_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $appointment->update([
            'staff_user_id' => $validated['staff_user_id'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => optional($appointment->scheduled_at)->format('Y-m'),
                'date' => optional($appointment->scheduled_at)->toDateString(),
            ])
            ->with('status', 'Staff assignment updated.');
    }

    public function storeWaitlist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'preferred_date' => ['required', 'date'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'preferred_end_time' => ['nullable', 'date_format:H:i', 'after:preferred_start_time'],
            'notes' => ['nullable', 'string'],
            'lead_source' => ['nullable', 'string', Rule::in(LeadSource::KEYS)],
        ]);

        $leadSource = $validated['lead_source'] ?? 'unknown';
        if (! is_string($leadSource) || ! in_array($leadSource, LeadSource::KEYS, true)) {
            $leadSource = 'unknown';
        }

        WaitlistEntry::query()->create([
            'customer_id' => $validated['customer_id'],
            'service_id' => $validated['service_id'] ?? null,
            'staff_user_id' => $validated['staff_user_id'] ?? null,
            'preferred_date' => $validated['preferred_date'],
            'preferred_start_time' => $validated['preferred_start_time'] ?? null,
            'preferred_end_time' => $validated['preferred_end_time'] ?? null,
            'status' => 'waiting',
            'lead_source' => $leadSource,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index', [
                'month' => Carbon::parse($validated['preferred_date'])->format('Y-m'),
                'date' => Carbon::parse($validated['preferred_date'])->toDateString(),
            ])
            ->with('status', 'Customer added to waitlist.');
    }

    public function recordWaitlistContact(Request $request, WaitlistEntry $waitlistEntry): RedirectResponse
    {
        $validated = $request->validate([
            'contact_method' => ['required', 'string', Rule::in(ContactMethod::KEYS)],
            'contact_notes' => ['required', 'string', 'min:1', 'max:10000'],
            'contacted_at' => ['required', 'date'],
            'return_to' => ['nullable', 'string', Rule::in(['leads', 'appointments'])],
        ]);

        $contactedAt = Carbon::parse($validated['contacted_at'], config('app.timezone'));

        $waitlistEntry->update([
            'status' => 'contacted',
            'contacted_at' => $contactedAt,
            'contact_method' => $validated['contact_method'],
            'contact_notes' => $validated['contact_notes'] ?? null,
            'contacted_by_user_id' => $request->user()->id,
        ]);

        $message = 'Contact logged and lead marked as contacted.';

        if (($validated['return_to'] ?? '') === 'leads') {
            return redirect()->route('leads.index')->with('status', $message);
        }

        return redirect()
            ->route('appointments.index', [
                'month' => optional($waitlistEntry->preferred_date)->format('Y-m'),
                'date' => optional($waitlistEntry->preferred_date)->toDateString(),
            ])
            ->with('status', $message);
    }

    public function updateWaitlistStatus(Request $request, WaitlistEntry $waitlistEntry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['waiting', 'booked', 'cancelled'])],
            'return_to' => ['nullable', 'string', Rule::in(['leads', 'appointments'])],
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'cancelled') {
            $updates['contacted_at'] = null;
            $updates['contact_method'] = null;
            $updates['contact_notes'] = null;
            $updates['contacted_by_user_id'] = null;
        }

        $waitlistEntry->update($updates);

        $message = 'Waitlist entry marked as '.$validated['status'].'.';

        if (($validated['return_to'] ?? '') === 'leads') {
            return redirect()->route('leads.index')->with('status', $message);
        }

        return redirect()
            ->route('appointments.index', [
                'month' => optional($waitlistEntry->preferred_date)->format('Y-m'),
                'date' => optional($waitlistEntry->preferred_date)->toDateString(),
            ])
            ->with('status', $message);
    }

    /**
     * Filtered appointments without eager loads (cheap for month grid counts).
     *
     * @return Builder<Appointment>
     */
    private function appointmentsFilteredQuery(Request $request): Builder
    {
        $status = (string) $request->query('status', '');
        $customerId = (int) $request->query('customer_id', 0);
        $search = trim((string) $request->query('search', ''));
        $serviceId = (int) $request->query('service_id', 0);
        $arrived = (string) $request->query('arrived', '');
        $staffUserId = (int) $request->query('staff_user_id', 0);

        return Appointment::query()
            ->when(in_array($status, ['booked', 'completed', 'cancelled', 'no_show'], true), function (Builder $query) use ($status) {
                $query->where('status', $status);
            })
            ->when($customerId > 0, function (Builder $query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->whereHas('customer', function (Builder $customerQuery) use ($search) {
                    $customerQuery->where(function (Builder $matchQuery) use ($search) {
                        $matchQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });
            })
            ->when($serviceId > 0, function (Builder $query) use ($serviceId) {
                $query->whereHas('services', function (Builder $serviceQuery) use ($serviceId) {
                    $serviceQuery->where('service_id', $serviceId);
                });
            })
            ->when(in_array($arrived, ['yes', 'no'], true), function (Builder $query) use ($arrived) {
                $query->where('arrived_confirmed', $arrived === 'yes');
            })
            ->when($staffUserId > 0, function (Builder $query) use ($staffUserId) {
                $query->where('staff_user_id', $staffUserId);
            });
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointmentsBaseQuery(Request $request): Builder
    {
        return $this->appointmentsFilteredQuery($request)
            ->with([
                // Nested with() passes Relation (e.g. BelongsTo), not Eloquent\Builder — do not type-hint Builder.
                'customer' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'email', 'phone')
                        ->with([
                            'memberships' => function ($memberships) {
                                $memberships
                                    ->select('id', 'customer_id', 'membership_id', 'start_date', 'end_date', 'status')
                                    ->with([
                                        'membership' => function ($membership) {
                                            $membership->select('id', 'name')
                                                ->with([
                                                    'coveredServices' => function ($services) {
                                                        $services->select('services.id');
                                                    },
                                                ]);
                                        },
                                    ]);
                            },
                        ]);
                },
                'services' => function ($query) {
                    $query->select(
                        'id',
                        'appointment_id',
                        'service_id',
                        'service_name',
                        'duration_minutes',
                        'quantity',
                        'unit_price',
                        'line_total',
                        'created_at',
                        'updated_at',
                    );
                },
                'staffUser:id,name',
                'cancelledBy:id,name',
            ]);
    }

    /**
     * @return array{status: string, customer_id: int, search: string, service_id: int, arrived: string, staff_user_id: int}
     */
    private function filterParamsFromRequest(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', ''),
            'customer_id' => (int) $request->query('customer_id', 0),
            'search' => trim((string) $request->query('search', '')),
            'service_id' => (int) $request->query('service_id', 0),
            'arrived' => (string) $request->query('arrived', ''),
            'staff_user_id' => (int) $request->query('staff_user_id', 0),
        ];
    }

    private function validateAppointmentPayload(Request $request): array
    {
        return $request->validate(array_merge([
            'customer_id' => ['required', 'exists:customers,id'],
            'staff_user_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:scheduled_at'],
            'notes' => ['nullable', 'string'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],
        ], AppointmentPolicyEnforcer::depositRulesForRequest()));
    }

    private function ensureNoStaffConflict(array $validated, ?Appointment $ignoreAppointment = null): void
    {
        $staffUserId = (int) ($validated['staff_user_id'] ?? 0);
        if ($staffUserId === 0) {
            return;
        }

        $start = Carbon::parse($validated['scheduled_at']);
        $end = ! empty($validated['ends_at'])
            ? Carbon::parse($validated['ends_at'])
            : $start->copy()->addMinutes($this->estimatedDurationMinutes($validated['services'] ?? []));

        $conflict = Appointment::query()
            ->where('staff_user_id', $staffUserId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($ignoreAppointment, function (Builder $query) use ($ignoreAppointment) {
                $query->where('id', '!=', $ignoreAppointment->id);
            })
            ->where(function (Builder $query) use ($start, $end) {
                $query
                    ->where('scheduled_at', '<', $end)
                    ->whereRaw('COALESCE(ends_at, DATE_ADD(scheduled_at, INTERVAL 60 MINUTE)) > ?', [$start->format('Y-m-d H:i:s')]);
            })
            ->with(['customer:id,first_name,last_name'])
            ->first();

        if (! $conflict) {
            return;
        }

        $name = trim((string) ($conflict->customer?->first_name.' '.$conflict->customer?->last_name));

        throw ValidationException::withMessages([
            'scheduled_at' => [
                'This staff member already has an overlapping appointment'
                .($name !== '' ? ' with '.$name : '')
                .' at '
                .optional($conflict->scheduled_at)->format('g:i A')
                .'.',
            ],
        ]);
    }

    private function estimatedDurationMinutes(array $serviceLines): int
    {
        $total = 0;

        foreach ($serviceLines as $line) {
            $service = Service::query()->find((int) ($line['service_id'] ?? 0));
            if (! $service) {
                continue;
            }

            $total += ((int) $service->duration_minutes) * max(1, (int) ($line['quantity'] ?? 1));
        }

        return max($total, 60);
    }

    private function buildStaffAvailability(Collection $appointments, Collection $staffUsers): Collection
    {
        $availability = $staffUsers->map(function (object $staff) use ($appointments) {
            $assigned = $appointments
                ->filter(fn (Appointment $appointment) => (int) $appointment->staff_user_id === (int) $staff->id)
                ->sortBy('scheduled_at')
                ->values();

            return [
                'label' => $staff->name,
                'appointments' => $assigned,
                'count' => $assigned->count(),
            ];
        });

        $unassigned = $appointments
            ->filter(fn (Appointment $appointment) => empty($appointment->staff_user_id))
            ->sortBy('scheduled_at')
            ->values();

        if ($unassigned->isNotEmpty()) {
            $availability->push([
                'label' => 'Unassigned',
                'appointments' => $unassigned,
                'count' => $unassigned->count(),
            ]);
        }

        return $availability;
    }

    private function waitlistEntriesForDate(Carbon $selectedDate): Collection
    {
        return WaitlistEntry::query()
            ->with([
                'customer:id,first_name,last_name,email,phone',
                'service:id,name',
                'staffUser:id,name',
                'contactedBy:id,name',
            ])
            ->whereDate('preferred_date', $selectedDate->toDateString())
            ->whereIn('status', ['waiting', 'contacted'])
            ->orderBy('preferred_start_time')
            ->orderBy('created_at')
            ->get();
    }

    private function syncAppointmentServices(Appointment $appointment, array $serviceLines): void
    {
        $appointment->services()->delete();
        $total = 0.0;

        foreach ($serviceLines as $line) {
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

        $appointment->update(['total_amount' => number_format($total, 2, '.', '')]);
    }

    private function parseDate(string $raw, Carbon $fallback): Carbon
    {
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    private function parseMonth(string $raw, Carbon $fallback): Carbon
    {
        $month = Carbon::createFromFormat('Y-m', $raw);

        return $month ?: $fallback->copy();
    }

    private function defaultSelectedDate(Collection $appointmentsByDate, Carbon $today, Carbon $monthBase): Carbon
    {
        $dateKeys = $appointmentsByDate->keys()
            ->map(fn (string $key) => Carbon::parse($key))
            ->sortBy(fn (Carbon $date) => $date->timestamp)
            ->values();

        $preferred = $dateKeys->first(function (Carbon $date) use ($today, $monthBase) {
            return $date->month === $monthBase->month
                && $date->year === $monthBase->year
                && $date->greaterThanOrEqualTo($today);
        });

        if ($preferred) {
            return $preferred->copy();
        }

        $inMonth = $dateKeys->first(function (Carbon $date) use ($monthBase) {
            return $date->month === $monthBase->month && $date->year === $monthBase->year;
        });

        return ($inMonth ?: $dateKeys->first() ?: $today)->copy();
    }
}
