<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebUiRedirectToFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.frontend_url', 'http://frontend.test');
    }

    public function test_get_login_redirects_to_spa_login(): void
    {
        $this->get('/login')->assertRedirect('http://frontend.test/login');
    }

    public function test_get_customers_redirects_to_spa_with_query_string(): void
    {
        $this->get('/customers?sort=name')->assertRedirect('http://frontend.test/customers?sort=name');
    }

    public function test_get_admin_control_board_redirects_to_spa(): void
    {
        $this->get('/admin/control-board')->assertRedirect('http://frontend.test/admin/control-board');
    }

    public function test_json_request_bypasses_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/appointments/day?date=2026-01-05')
            ->assertStatus(410);
    }

    public function test_post_login_is_handled_by_laravel_not_redirected_to_spa_root(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret-password'),
        ]);

        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $location = (string) $response->headers->get('Location');
        $this->assertNotSame('http://frontend.test/', $location);
        $this->assertNotSame('http://frontend.test', $location);
    }
}
