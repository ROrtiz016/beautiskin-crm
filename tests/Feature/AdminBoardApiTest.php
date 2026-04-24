<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBoardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_api_routes(): void
    {
        $this->patchJson('/api/admin/operations/appointment-policy', [
            'appointment_cancellation_hours' => 24,
            'deposit_required' => false,
        ])->assertUnauthorized();
    }

    public function test_user_without_admin_board_access_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => [],
        ]));

        $this->patchJson('/api/admin/operations/appointment-policy', [
            'appointment_cancellation_hours' => 24,
            'deposit_required' => false,
        ])->assertForbidden();
    }

    public function test_admin_can_patch_appointment_policy_as_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson('/api/admin/operations/appointment-policy', [
            'appointment_cancellation_hours' => 12,
            'deposit_required' => true,
            'default_deposit_amount' => 25,
            'max_bookings_per_day' => 40,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Appointment policy saved.')
            ->assertJsonStructure(['clinicSettings']);
    }

    public function test_manager_with_manage_users_can_patch_appointment_policy(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => ['manage_users'],
        ]));

        $this->patchJson('/api/admin/operations/appointment-policy', [
            'appointment_cancellation_hours' => 6,
            'deposit_required' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Appointment policy saved.');
    }

    public function test_non_admin_cannot_patch_feature_flags(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => ['manage_users'],
        ]));

        $this->patchJson('/api/admin/operations/feature-flags', [
            'experimental_ui' => true,
        ])->assertForbidden();
    }

    public function test_admin_can_patch_service_price_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $service = Service::query()->create([
            'name' => 'Test Facial',
            'price' => '99.00',
        ]);

        $this->patchJson("/api/admin/services/{$service->id}/price", ['price' => 120.5])
            ->assertOk()
            ->assertJsonPath('message', 'Service price updated.')
            ->assertJsonPath('service.price', '120.50');
    }

    public function test_admin_can_download_reports_csv(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->get('/api/admin/reports/export?from=2026-01-01&to=2026-01-07');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', strtolower((string) $response->headers->get('content-disposition')));
        $this->assertStringContainsString('BeautiSkin CRM', (string) $response->streamedContent());
    }

    public function test_admin_can_schedule_and_cancel_price_change(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);
        $service = Service::query()->create([
            'name' => 'Schedule Test',
            'price' => '50.00',
        ]);

        $effective = now()->addDay()->toIso8601String();

        $store = $this->postJson('/api/admin/scheduled-price-changes', [
            'priceable' => 'service:'.$service->id,
            'new_price' => 55,
            'effective_at' => $effective,
        ]);
        $store->assertOk()->assertJsonStructure(['scheduledPriceChange' => ['id']]);
        $id = (int) $store->json('scheduledPriceChange.id');

        $this->postJson("/api/admin/scheduled-price-changes/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('scheduledPriceChange.status', 'cancelled');
    }

    public function test_admin_can_patch_membership_price(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $membership = Membership::query()->create([
            'name' => 'Gold',
            'monthly_price' => '79.00',
            'billing_cycle_days' => 30,
        ]);

        $this->patchJson("/api/admin/memberships/{$membership->id}/price", ['price' => 89])
            ->assertOk()
            ->assertJsonPath('membership.monthly_price', '89.00');
    }

    public function test_admin_can_toggle_promotion_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $promotion = Promotion::query()->create([
            'name' => 'Spring',
            'discount_type' => 'percent',
            'discount_value' => '10.00',
            'applies_to' => 'all',
            'is_active' => true,
            'stackable' => false,
        ]);

        $this->patchJson("/api/admin/promotions/{$promotion->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('promotion.is_active', false);
    }

    public function test_admin_can_patch_clinic_tax_settings_as_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson('/api/admin/clinic-settings', [
            'default_tax_rate' => 0.0775,
            'price_rounding_rule' => 'ceil',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Tax and rounding settings saved.')
            ->assertJsonPath('clinicSettings.price_rounding_rule', 'ceil');
    }

    public function test_admin_can_create_user_as_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson('/api/admin/users', [
            'name' => 'API Invite',
            'email' => 'api-invite@example.test',
            'password' => 'GoodPass1!',
            'password_confirmation' => 'GoodPass1!',
            'is_admin' => false,
            'role_template' => 'custom',
            'permissions' => [],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('user.email', 'api-invite@example.test')
            ->assertJsonPath('user.is_admin', false);
    }

    public function test_admin_can_put_promotion_rules_as_json(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $promotion = Promotion::query()->create([
            'name' => 'Summer',
            'discount_type' => 'percent',
            'discount_value' => '10.00',
            'applies_to' => 'all',
            'is_active' => true,
            'stackable' => false,
        ]);

        $this->putJson("/api/admin/promotions/{$promotion->id}", [
            'name' => 'Summer Sale',
            'description' => 'Updated via API',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'applies_to' => 'all',
            'starts_on' => null,
            'ends_on' => null,
            'stackable' => true,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Promotion rules updated.')
            ->assertJsonPath('promotion.name', 'Summer Sale')
            ->assertJsonPath('promotion.discount_value', '15.00');
    }
}
