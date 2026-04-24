<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AppointmentsFrontendRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_appointments_when_no_spa(): void
    {
        Config::set('app.frontend_url', null);

        $this->get('/appointments')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_spa_when_frontend_url_set(): void
    {
        Config::set('app.frontend_url', 'http://localhost:3000');

        $this->get('/appointments')->assertRedirect('http://localhost:3000/appointments');
    }

    public function test_authenticated_user_sees_blade_calendar_when_frontend_url_empty(): void
    {
        Config::set('app.frontend_url', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/appointments')
            ->assertOk();
    }

    public function test_authenticated_user_redirected_to_spa_when_frontend_url_set(): void
    {
        Config::set('app.frontend_url', 'http://localhost:3000');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/appointments?month=2026-01&date=2026-01-05')
            ->assertRedirect('http://localhost:3000/appointments?month=2026-01&date=2026-01-05');
    }

    public function test_day_fragment_returns_gone_when_frontend_url_set(): void
    {
        Config::set('app.frontend_url', 'http://localhost:3000');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/appointments/day?date=2026-01-05')
            ->assertStatus(410)
            ->assertJsonStructure(['message']);
    }

    public function test_authenticated_user_gets_day_fragment_when_frontend_url_empty(): void
    {
        Config::set('app.frontend_url', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/appointments/day?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonStructure(['html', 'date', 'month']);
    }
}
