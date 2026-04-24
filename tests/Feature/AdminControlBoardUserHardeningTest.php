<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControlBoardUserHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_without_admin_flag_cannot_create_administrator_via_api(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => ['manage_users'],
        ]));

        $this->postJson('/api/admin/users', [
            'name' => 'Staff',
            'email' => 'staff-hardening@example.test',
            'password' => 'GoodPass1!',
            'password_confirmation' => 'GoodPass1!',
            'is_admin' => true,
            'role_template' => 'custom',
            'permissions' => [],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only administrators can create administrator accounts.');
    }

    public function test_manager_cannot_grant_administrator_flag_via_api(): void
    {
        $manager = User::factory()->create([
            'is_admin' => false,
            'permissions' => ['manage_users'],
        ]);
        $target = User::factory()->create([
            'is_admin' => false,
            'permissions' => [],
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/admin/users/{$target->id}/access", [
            'is_admin' => true,
            'permissions' => [],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only administrators can grant or revoke administrator access.');
    }

    public function test_cannot_remove_last_active_administrator_via_api(): void
    {
        $soloAdmin = User::factory()->create([
            'is_admin' => true,
            'permissions' => [],
        ]);

        Sanctum::actingAs($soloAdmin);

        $this->patchJson("/api/admin/users/{$soloAdmin->id}/access", [
            'is_admin' => false,
            'permissions' => [],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot remove the last active administrator.');
    }

    public function test_user_without_board_access_cannot_read_control_board_payload(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => ['seller'],
        ]));

        $this->getJson('/api/spa/admin/control-board')->assertForbidden();
    }
}
