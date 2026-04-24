<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerCommunicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_communication_returns_created(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $response = $this->postJson("/api/customers/{$customer->id}/communications", [
            'channel' => 'call',
            'summary' => 'Discussed follow-up visit.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Communication logged.');
    }

    public function test_manual_communication_validates_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->postJson("/api/customers/{$customer->id}/communications", [
            'channel' => 'fax',
            'summary' => 'Test',
        ])->assertUnprocessable();
    }

    public function test_templated_reminder_requires_appointment(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create();

        $this->postJson("/api/customers/{$customer->id}/communications/templated", [
            'template' => 'reminder',
            'channel' => 'email',
        ])->assertStatus(422);
    }
}
