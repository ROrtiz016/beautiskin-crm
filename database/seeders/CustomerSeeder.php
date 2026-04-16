<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::factory(24)->create();
        $staffUserIds = User::query()->pluck('id')->all();

        // Seed past completed appointments for history and payments
        foreach ($customers->random(10) as $customer) {
            $appointmentCount = random_int(1, 4);

            for ($i = 0; $i < $appointmentCount; $i++) {
                $scheduledAt = Carbon::now()->subDays(random_int(1, 120))->setTime(random_int(9, 18), 0);
                $total = random_int(80, 450);

                Appointment::query()->create([
                    'customer_id' => $customer->id,
                    'staff_user_id' => $staffUserIds !== [] ? $staffUserIds[array_rand($staffUserIds)] : null,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => (clone $scheduledAt)->addMinutes(random_int(30, 90)),
                    'status' => 'completed',
                    'total_amount' => number_format($total, 2, '.', ''),
                    'notes' => 'Seeded historical appointment',
                ]);
            }
        }

        // Seed future booked appointments so profile sections show data
        foreach ($customers->random(14) as $customer) {
            $bookedCount = random_int(1, 3);

            for ($i = 0; $i < $bookedCount; $i++) {
                $scheduledAt = Carbon::now()
                    ->addDays(random_int(1, 45))
                    ->setTime(random_int(9, 18), random_int(0, 1) * 30);
                $estimatedTotal = random_int(90, 500);

                Appointment::query()->create([
                    'customer_id' => $customer->id,
                    'staff_user_id' => $staffUserIds !== [] ? $staffUserIds[array_rand($staffUserIds)] : null,
                    'scheduled_at' => $scheduledAt,
                    'ends_at' => (clone $scheduledAt)->addMinutes(random_int(30, 90)),
                    'status' => 'booked',
                    'total_amount' => number_format($estimatedTotal, 2, '.', ''),
                    'notes' => 'Seeded upcoming booked appointment',
                ]);
            }
        }
    }
}
