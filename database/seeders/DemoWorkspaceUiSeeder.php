<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\ContactMethod;
use App\Support\LeadSource;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Idempotent demo rows for SPA lists: customers, tasks, activity feed, appointments, waitlist (leads).
 *
 * Cleans up prior run via dedicated demo customer emails (demo-ui-*@example.test).
 */
class DemoWorkspaceUiSeeder extends Seeder
{
    private const EMAIL_PREFIX = 'demo-ui-';

    private const EMAIL_SUFFIX = '@example.test';

    private const MARKER = ' [demo-ui-seed]';

    public function run(): void
    {
        $this->cleanup();

        $users = User::query()->whereNull('deleted_at')->get();
        if ($users->isEmpty()) {
            $this->command?->warn('DemoWorkspaceUiSeeder: no users found; seed users first.');

            return;
        }

        $userIds = $users->pluck('id')->all();
        $pickUserId = static fn (): int => (int) $userIds[array_rand($userIds)];

        $tz = config('app.timezone');
        $serviceIds = Service::query()->pluck('id')->all();
        $servicePick = static fn (): ?int => $serviceIds !== [] ? (int) $serviceIds[array_rand($serviceIds)] : null;

        /** @var Collection<int, Customer> $customers */
        $customers = collect();
        for ($i = 1; $i <= 16; $i++) {
            $customers->push(Customer::factory()->create([
                'email' => self::EMAIL_PREFIX.$i.self::EMAIL_SUFFIX,
                'notes' => 'Demo workspace UI customer'.self::MARKER,
            ]));
        }

        // Past + future appointments (no line items; enough for calendar / lists)
        foreach ($customers->random(12) as $customer) {
            for ($n = 0; $n < random_int(1, 2); $n++) {
                $scheduledAt = Carbon::now($tz)->subDays(random_int(1, 55))->setTime(random_int(9, 17), random_int(0, 1) * 30);
                $this->createAppointment($customer->id, $pickUserId(), $scheduledAt, 'completed');
            }
        }
        foreach ($customers->random(11) as $customer) {
            for ($n = 0; $n < random_int(1, 2); $n++) {
                $scheduledAt = Carbon::now($tz)->addDays(random_int(1, 28))->setTime(random_int(9, 16), random_int(0, 1) * 30);
                $this->createAppointment($customer->id, $pickUserId(), $scheduledAt, 'booked');
            }
        }

        // A few visits with one service line (inventory / revenue views)
        $primaryService = Service::query()->where('is_active', true)->orderBy('id')->first();
        if ($primaryService !== null) {
            foreach ($customers->random(5) as $customer) {
                $scheduledAt = Carbon::now($tz)->subDays(random_int(3, 30))->setTime(11, 0);
                $appointment = $this->createAppointment($customer->id, $pickUserId(), $scheduledAt, 'completed');
                $unit = (float) $primaryService->price;
                $qty = 1;
                $lineTotal = round($unit * $qty, 2);
                AppointmentService::query()->create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $primaryService->id,
                    'service_name' => $primaryService->name,
                    'duration_minutes' => (int) $primaryService->duration_minutes,
                    'quantity' => $qty,
                    'unit_price' => number_format($unit, 2, '.', ''),
                    'line_total' => number_format($lineTotal, 2, '.', ''),
                ]);
                $appointment->update([
                    'total_amount' => number_format($lineTotal, 2, '.', ''),
                    'notes' => 'Demo visit with service line'.self::MARKER,
                ]);
            }
        }

        // Waitlist / leads
        $statuses = ['waiting', 'waiting', 'waiting', 'contacted', 'contacted', 'booked', 'cancelled'];
        foreach ($customers->random(14) as $customer) {
            $preferred = Carbon::now($tz)->startOfDay()->addDays(random_int(-3, 24));
            $status = $statuses[array_rand($statuses)];
            $leadSource = LeadSource::KEYS[array_rand(LeadSource::KEYS)];
            $startHour = random_int(0, 10) < 7 ? random_int(9, 15) : null;
            $preferredStart = $startHour !== null ? sprintf('%02d:00:00', $startHour) : null;
            $preferredEnd = null;
            if ($preferredStart !== null) {
                $endHour = min(19, $startHour + random_int(1, 2));
                $preferredEnd = sprintf('%02d:30:00', $endHour);
            }
            $contactedAt = $status === 'contacted'
                ? Carbon::now($tz)->subHours(random_int(4, 96))
                : null;

            WaitlistEntry::query()->create([
                'customer_id' => $customer->id,
                'service_id' => $servicePick(),
                'staff_user_id' => random_int(0, 10) < 6 ? $pickUserId() : null,
                'preferred_date' => $preferred->toDateString(),
                'preferred_start_time' => $preferredStart,
                'preferred_end_time' => $preferredEnd,
                'status' => $status,
                'lead_source' => $leadSource,
                'contacted_at' => $contactedAt,
                'contact_method' => $status === 'contacted' ? ContactMethod::KEYS[array_rand(ContactMethod::KEYS)] : null,
                'contact_notes' => $status === 'contacted' ? 'Demo outreach: left voicemail and sent follow-up pricing.'.self::MARKER : null,
                'contacted_by_user_id' => $status === 'contacted' ? $pickUserId() : null,
                'notes' => 'Demo waitlist lead'.self::MARKER,
            ]);
        }

        // Tasks
        $kinds = [
            Task::KIND_FOLLOW_UP,
            Task::KIND_CALLBACK,
            Task::KIND_GENERAL,
            Task::KIND_VISIT_PREP,
            Task::KIND_PREP,
        ];
        foreach ($customers as $customer) {
            $taskCount = random_int(1, 3);
            for ($t = 0; $t < $taskCount; $t++) {
                $status = fake()->randomElement(['pending', 'pending', 'pending', 'completed', 'cancelled']);
                $dueAt = Carbon::now($tz)->addDays(random_int(-10, 18))->setTime(random_int(9, 17), 0);
                $completedAt = $status === 'completed' ? $dueAt->copy()->subHours(random_int(1, 48)) : null;
                $completedBy = $status === 'completed' ? $pickUserId() : null;

                Task::query()->create([
                    'customer_id' => $customer->id,
                    'opportunity_id' => null,
                    'assigned_to_user_id' => $pickUserId(),
                    'created_by_user_id' => $pickUserId(),
                    'completed_by_user_id' => $completedBy,
                    'kind' => $kinds[array_rand($kinds)],
                    'title' => fake()->randomElement([
                        'Follow up after consult',
                        'Confirm patch test',
                        'Send pre-visit instructions',
                        'Collect deposit',
                        'Book follow-up facial',
                        'Return missed call',
                    ]),
                    'description' => 'Demo task for workspace UI.'.self::MARKER,
                    'due_at' => $dueAt,
                    'remind_at' => $status === 'pending' && random_int(0, 1) === 1 ? $dueAt->copy()->subDay() : null,
                    'status' => $status,
                    'completed_at' => $completedAt,
                ]);
            }
        }

        // Activity feed (spread created_at for filters / timeline)
        $rows = [
            [
                'event_type' => CustomerActivity::EVENT_NOTE_ADDED,
                'category' => CustomerActivity::CATEGORY_NOTE,
                'summary' => 'Staff note: reviewed home care routine and SPF use.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_CALL_LOGGED,
                'category' => CustomerActivity::CATEGORY_COMMUNICATION,
                'summary' => 'Outbound call: confirmed appointment window and parking.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_EMAIL_SENT,
                'category' => CustomerActivity::CATEGORY_COMMUNICATION,
                'summary' => 'Sent pre-visit prep email with contraindications checklist.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_APPOINTMENT_CREATED,
                'category' => CustomerActivity::CATEGORY_APPOINTMENT,
                'summary' => 'Booked facial — deposit collected.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_APPOINTMENT_STATUS,
                'category' => CustomerActivity::CATEGORY_APPOINTMENT,
                'summary' => 'Visit marked completed; retail add-ons discussed.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_TASK_CREATED,
                'category' => CustomerActivity::CATEGORY_TASK,
                'summary' => 'Task created: callback after patch test results.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_OPPORTUNITY_STAGE_CHANGED,
                'category' => CustomerActivity::CATEGORY_SALES,
                'summary' => 'Opportunity moved to proposal — membership bundle quoted.',
            ],
            [
                'event_type' => CustomerActivity::EVENT_PAYMENT_COMPLETED_VISIT,
                'category' => CustomerActivity::CATEGORY_PAYMENT,
                'summary' => 'Payment recorded for completed visit (card on file).',
            ],
        ];

        $pool = $customers->shuffle()->values();
        $idx = 0;
        foreach (range(1, 42) as $n) {
            $customer = $pool[$idx % $pool->count()];
            $idx++;
            $tpl = $rows[($n - 1) % count($rows)];
            $when = Carbon::now($tz)->subDays(random_int(0, 58))->subHours(random_int(0, 20));

            $activity = CustomerActivity::query()->create([
                'customer_id' => $customer->id,
                'user_id' => random_int(0, 10) < 8 ? $pickUserId() : null,
                'event_type' => $tpl['event_type'],
                'category' => $tpl['category'],
                'summary' => $tpl['summary'].self::MARKER,
                'meta' => ['demo_seed' => true, 'seq' => $n],
                'related_task_id' => null,
            ]);
            $activity->forceFill([
                'created_at' => $when,
                'updated_at' => $when,
            ])->saveQuietly();
        }
    }

    private function createAppointment(int $customerId, int $staffUserId, Carbon $scheduledAt, string $status): Appointment
    {
        $total = random_int(85, 420);

        return Appointment::query()->create([
            'customer_id' => $customerId,
            'staff_user_id' => random_int(0, 10) < 8 ? $staffUserId : null,
            'scheduled_at' => $scheduledAt,
            'ends_at' => (clone $scheduledAt)->addMinutes(random_int(35, 85)),
            'status' => $status,
            'total_amount' => number_format($total, 2, '.', ''),
            'notes' => 'Demo workspace appointment'.self::MARKER,
        ]);
    }

    private function cleanup(): void
    {
        $ids = Customer::withTrashed()
            ->where('email', 'like', self::EMAIL_PREFIX.'%'.self::EMAIL_SUFFIX)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $appointmentIds = Appointment::query()->whereIn('customer_id', $ids)->pluck('id');
        if ($appointmentIds->isNotEmpty()) {
            AppointmentService::query()->whereIn('appointment_id', $appointmentIds)->delete();
            Appointment::query()->whereIn('id', $appointmentIds)->delete();
        }

        WaitlistEntry::query()->whereIn('customer_id', $ids)->delete();
        CustomerActivity::query()->whereIn('customer_id', $ids)->delete();
        Task::query()->whereIn('customer_id', $ids)->delete();

        Customer::withTrashed()->whereIn('id', $ids)->forceDelete();
    }
}
