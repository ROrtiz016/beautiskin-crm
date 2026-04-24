<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1 (###) ###-####'),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Female', 'Male', 'Non-binary']),
            'address_line1' => fake()->optional(0.7)->streetAddress(),
            'address_line2' => fake()->optional(0.15)->secondaryAddress(),
            'city' => fake()->optional(0.7)->city(),
            'state_region' => fake()->optional(0.7)->randomElement([
                'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA',
                'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM',
                'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA',
                'WV', 'WI', 'WY',
            ]),
            'postal_code' => fake()->optional(0.7)->postcode(),
            'country' => fake()->optional(0.7)->randomElement(['US', 'CA', 'MX', 'GB', 'AU']),
            'notes' => fake()->optional(0.6)->sentence(),
        ];
    }
}
