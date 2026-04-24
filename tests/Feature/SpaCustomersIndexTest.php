<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpaCustomersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_spa_customers_index(): void
    {
        $this->getJson('/api/spa/customers')->assertUnauthorized();
    }

    public function test_authenticated_user_receives_paginated_customers_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Customer::factory()->create([
            'first_name' => 'Zara',
            'last_name' => 'Unique',
            'email' => 'zara.unique@example.test',
        ]);

        $response = $this->getJson('/api/spa/customers?search=Unique&sort=name&direction=asc');

        $response->assertOk()
            ->assertJsonPath('search', 'Unique')
            ->assertJsonPath('sort', 'name')
            ->assertJsonPath('direction', 'asc')
            ->assertJsonStructure([
                'customers' => [
                    'data',
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('customers.data')));
    }
}
