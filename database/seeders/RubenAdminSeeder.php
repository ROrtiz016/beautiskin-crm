<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RubenAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'ruben@example.com';

        $user = User::withTrashed()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Ruben Ortiz',
                'password' => 'password',
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'name' => 'Ruben Ortiz',
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'remember_token' => $user->remember_token ?: Str::random(10),
        ])->save();
    }
}
