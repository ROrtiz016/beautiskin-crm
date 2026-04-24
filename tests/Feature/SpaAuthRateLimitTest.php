<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpaAuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_login_clears_lockout_after_success(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_forgot_password_throttles_by_ip(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create();
            $this->postJson('/api/auth/forgot-password', [
                'email' => $user->email,
            ])->assertOk();
        }

        $sixth = User::factory()->create();
        $this->postJson('/api/auth/forgot-password', [
            'email' => $sixth->email,
        ])->assertStatus(429);
    }
}
