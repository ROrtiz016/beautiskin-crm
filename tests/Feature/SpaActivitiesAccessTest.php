<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectWebUiToFrontend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpaActivitiesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_spa_activities(): void
    {
        $this->getJson('/api/spa/activities')->assertUnauthorized();
    }

    public function test_non_admin_user_without_manage_users_cannot_fetch_spa_activities(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => [],
        ]));

        $this->getJson('/api/spa/activities')->assertForbidden();
    }

    public function test_admin_user_can_fetch_spa_activities(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => true,
        ]));

        $this->getJson('/api/spa/activities')
            ->assertOk()
            ->assertJsonStructure([
                'title',
                'activities' => [
                    'data',
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'categoryLabels',
            ]);
    }

    public function test_user_with_manage_users_permission_can_fetch_spa_activities(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => false,
            'permissions' => ['manage_users'],
        ]));

        $this->getJson('/api/spa/activities')->assertOk();
    }

    public function test_non_admin_cannot_open_activity_web_page(): void
    {
        $this->withoutMiddleware(RedirectWebUiToFrontend::class);

        $user = User::factory()->create([
            'is_admin' => false,
            'permissions' => [],
        ]);

        $this->actingAs($user);
        $this->get(route('activity.index'))->assertForbidden();
    }

    public function test_admin_can_open_activity_web_page(): void
    {
        $this->withoutMiddleware(RedirectWebUiToFrontend::class);

        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user);
        $this->get(route('activity.index'))->assertOk();
    }
}
