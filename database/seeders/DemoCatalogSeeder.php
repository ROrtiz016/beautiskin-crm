<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Customer;
use App\Models\CustomerMembership;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\AppointmentFormLookupCache;
use App\Support\ContactMethod;
use App\Support\LeadSource;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoCatalogSeeder extends Seeder
{
    private const MARKER = '[demo-catalog-seed]';

    public function run(): void
    {
        $this->cleanupPreviousDemo();

        $staffIds = User::query()->pluck('id')->all();
        $staffPick = static fn () => $staffIds !== [] ? $staffIds[array_rand($staffIds)] : null;

        $services = $this->seedServices();
        $memberships = $this->seedMemberships();

        $customers = Customer::query()->inRandomOrder()->limit(20)->get();
        if ($customers->isEmpty()) {
            $this->command?->warn('No customers found; run CustomerSeeder first for demo sales.');

            return;
        }

        $this->seedCustomerMemberships($customers, $memberships);
        $this->seedAppointmentsWithLines($customers, $services, $staffPick);
        $this->seedDemoWaitlist($customers, $services, $staffPick);

        AppointmentFormLookupCache::forgetAll();
    }

    private function cleanupPreviousDemo(): void
    {
        $appointmentIds = Appointment::query()
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->pluck('id');

        if ($appointmentIds->isNotEmpty()) {
            AppointmentService::query()->whereIn('appointment_id', $appointmentIds)->delete();
            Appointment::query()->whereIn('id', $appointmentIds)->delete();
        }

        CustomerMembership::query()
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->delete();

        WaitlistEntry::query()
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->delete();
    }

    /**
     * @return array<string, Service>
     */
    private function seedServices(): array
    {
        $rows = [
            ['name' => 'HydraFacial MD', 'category' => 'Treatments', 'duration_minutes' => 60, 'price' => 199.00, 'description' => 'Deep cleanse and hydration.'],
            ['name' => 'Chemical peel (light)', 'category' => 'Treatments', 'duration_minutes' => 45, 'price' => 175.00, 'description' => 'Brightening peel series.'],
            ['name' => 'Dermaplaning', 'category' => 'Treatments', 'duration_minutes' => 40, 'price' => 125.00, 'description' => 'Manual exfoliation.'],
            ['name' => 'LED light therapy', 'category' => 'Treatments', 'duration_minutes' => 30, 'price' => 85.00, 'description' => 'Calming LED session.'],
            ['name' => 'Restorative serum kit', 'category' => 'Product', 'duration_minutes' => 0, 'price' => 48.00, 'description' => 'Take-home serum trio.'],
            ['name' => 'SPF 50 daily defense', 'category' => 'Retail', 'duration_minutes' => 0, 'price' => 36.00, 'description' => 'Broad spectrum sunscreen.'],
            ['name' => 'Cooling eye pads (pair)', 'category' => 'Products', 'duration_minutes' => 0, 'price' => 22.00, 'description' => 'Single-use soothing pads.'],
            ['name' => 'Lip renewal balm', 'category' => 'Retail', 'duration_minutes' => 0, 'price' => 18.00, 'description' => 'Peptide lip treatment.'],
        ];

        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['name']] = Service::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'category' => $row['category'],
                    'duration_minutes' => $row['duration_minutes'],
                    'price' => number_format((float) $row['price'], 2, '.', ''),
                    'description' => $row['description'],
                    'is_active' => true,
                ]
            );
        }

        return $byName;
    }

    /**
     * @return array<string, Membership>
     */
    private function seedMemberships(): array
    {
        $plans = [
            ['name' => 'Glow Club', 'monthly_price' => 129.00, 'billing_cycle_days' => 30, 'description' => 'Monthly facial credit + discounts.'],
            ['name' => 'Elite Aesthetics', 'monthly_price' => 249.00, 'billing_cycle_days' => 30, 'description' => 'Premium tier with priority booking.'],
            ['name' => 'Mini Refresh', 'monthly_price' => 59.00, 'billing_cycle_days' => 30, 'description' => 'Entry plan for seasonal visits.'],
        ];

        $byName = [];
        foreach ($plans as $plan) {
            $byName[$plan['name']] = Membership::query()->updateOrCreate(
                ['name' => $plan['name']],
                [
                    'monthly_price' => number_format((float) $plan['monthly_price'], 2, '.', ''),
                    'billing_cycle_days' => $plan['billing_cycle_days'],
                    'description' => $plan['description'],
                    'is_active' => true,
                ]
            );
        }

        return $byName;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, Membership>  $memberships
     */
    private function seedCustomerMemberships($customers, array $memberships): void
    {
        $plans = array_values($memberships);
        $tz = config('app.timezone');

        foreach ($customers->random(min(12, $customers->count())) as $customer) {
            $plan = $plans[array_rand($plans)];
            $created = Carbon::now($tz)->subDays(random_int(0, 85))->subHours(random_int(0, 12));
            $start = $created->copy()->startOfDay();

            $cm = CustomerMembership::query()->create([
                'customer_id' => $customer->id,
                'membership_id' => $plan->id,
                'start_date' => $start->toDateString(),
                'end_date' => null,
                'status' => 'active',
                'notes' => 'Demo subscription '.self::MARKER,
            ]);

            CustomerMembership::query()->whereKey($cm->id)->update([
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }

        // Extra volume on popular plan
        $glow = $memberships['Glow Club'] ?? null;
        if ($glow) {
            foreach ($customers->random(min(8, $customers->count())) as $customer) {
                if (CustomerMembership::query()->where('customer_id', $customer->id)->where('membership_id', $glow->id)->exists()) {
                    continue;
                }
                $created = Carbon::now($tz)->subDays(random_int(0, 80));
                $start = $created->copy()->startOfDay();
                $cm = CustomerMembership::query()->create([
                    'customer_id' => $customer->id,
                    'membership_id' => $glow->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => null,
                    'status' => 'active',
                    'notes' => 'Demo Glow Club '.self::MARKER,
                ]);
                CustomerMembership::query()->whereKey($cm->id)->update([
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, Service>  $services
     * @param  callable(): ?int  $staffPick
     */
    private function seedAppointmentsWithLines($customers, array $services, callable $staffPick): void
    {
        $tz = config('app.timezone');
        $serviceList = array_values($services);
        $treatmentServices = array_values(array_filter($serviceList, fn (Service $s) => ! in_array(strtolower((string) $s->category), ['product', 'products', 'retail'], true)));
        $productServices = array_values(array_filter($serviceList, fn (Service $s) => in_array(strtolower(trim((string) $s->category)), ['product', 'products', 'retail'], true)));

        foreach ($customers->random(min(16, $customers->count())) as $customer) {
            $visitCount = random_int(1, 3);
            for ($v = 0; $v < $visitCount; $v++) {
                $scheduledAt = Carbon::now($tz)->subDays(random_int(1, 88))->setTime(random_int(9, 17), random_int(0, 1) * 30);
                $status = random_int(1, 10) <= 8 ? 'completed' : 'booked';

                $appointment = Appointment::query()->create([
                    'customer_id' => $customer->id,
                    'staff_user_id' => $staffPick(),
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => (clone $scheduledAt)->addMinutes(random_int(45, 90)),
                    'status' => $status,
                    'total_amount' => '0.00',
                    'notes' => 'Demo visit with line items '.self::MARKER,
                ]);

                $lines = [];
                $primary = $treatmentServices[array_rand($treatmentServices)];
                $lines[] = $this->makeLine($appointment, $primary, random_int(1, 1));

                if ($productServices !== [] && random_int(1, 10) <= 6) {
                    $extra = $productServices[array_rand($productServices)];
                    $lines[] = $this->makeLine($appointment, $extra, random_int(1, 3));
                }

                if (random_int(1, 10) <= 4 && count($treatmentServices) > 1) {
                    $secondary = $treatmentServices[array_rand($treatmentServices)];
                    if ($secondary->id !== $primary->id) {
                        $lines[] = $this->makeLine($appointment, $secondary, 1);
                    }
                }

                $sum = array_sum(array_column($lines, 'line_total'));
                $appointment->update([
                    'total_amount' => number_format($sum, 2, '.', ''),
                ]);
            }
        }
    }

    /**
     * @return array{line_total: float}
     */
    private function makeLine(Appointment $appointment, Service $service, int $quantity): array
    {
        $unit = (float) $service->price;
        $lineTotal = round($unit * $quantity, 2);

        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => (int) $service->duration_minutes,
            'quantity' => $quantity,
            'unit_price' => number_format($unit, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
        ]);

        return ['line_total' => $lineTotal];
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  array<string, Service>  $services
     * @param  callable(): ?int  $staffPick
     */
    private function seedDemoWaitlist(Collection $customers, array $services, callable $staffPick): void
    {
        $tz = config('app.timezone');
        $serviceList = array_values($services);
        $serviceIds = array_map(static fn (Service $s) => $s->id, $serviceList);

        $statuses = ['waiting', 'waiting', 'waiting', 'contacted', 'contacted', 'booked', 'cancelled'];
        $noteSnippets = [
            'Prefers morning slots.',
            'Returning guest — VIP package interest.',
            'Price-sensitive; follow up with promo.',
            'Interested in HydraFacial series.',
            'Flexible on provider.',
            null,
        ];

        $sourcePool = [
            'social_instagram', 'social_instagram', 'social_facebook', 'social_tiktok', 'social_x',
            'google_ads', 'google_ads', 'website', 'website', 'referral', 'walk_in', 'email_campaign',
            'phone_inquiry', 'event', 'partner', 'other', 'unknown',
        ];

        $pool = $customers->shuffle()->take(min(22, $customers->count()));
        foreach ($pool as $customer) {
            $preferred = Carbon::now($tz)->startOfDay()->addDays(random_int(-5, 28));
            $status = $statuses[array_rand($statuses)];
            $leadSource = $sourcePool[array_rand($sourcePool)];
            if (! in_array($leadSource, LeadSource::KEYS, true)) {
                $leadSource = 'unknown';
            }

            $startHour = random_int(0, 10) < 7 ? random_int(9, 15) : null;
            $preferredStart = $startHour !== null ? sprintf('%02d:00:00', $startHour) : null;
            $preferredEnd = null;
            if ($preferredStart !== null) {
                $endHour = min(19, $startHour + random_int(1, 2));
                $preferredEnd = sprintf('%02d:30:00', $endHour);
            }

            $snippet = $noteSnippets[array_rand($noteSnippets)];
            $notes = ($snippet !== null && $snippet !== '' ? $snippet.' ' : '').'Demo waitlist '.self::MARKER;

            $contactedById = $staffPick();
            $contactMethod = ContactMethod::KEYS[array_rand(ContactMethod::KEYS)];
            $contactedAt = $status === 'contacted'
                ? Carbon::now($tz)->subHours(random_int(2, 120))
                : null;

            WaitlistEntry::query()->create([
                'customer_id' => $customer->id,
                'service_id' => $serviceIds !== [] ? $serviceIds[array_rand($serviceIds)] : null,
                'staff_user_id' => random_int(0, 10) < 6 ? $staffPick() : null,
                'preferred_date' => $preferred->toDateString(),
                'preferred_start_time' => $preferredStart,
                'preferred_end_time' => $preferredEnd,
                'status' => $status,
                'lead_source' => $leadSource,
                'contacted_at' => $contactedAt,
                'contact_method' => $status === 'contacted' ? $contactMethod : null,
                'contact_notes' => $status === 'contacted' ? 'Demo outreach: discussed pricing and next available facial slot.' : null,
                'contacted_by_user_id' => $status === 'contacted' ? $contactedById : null,
                'notes' => $notes,
            ]);
        }
    }
}
