<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->seedUserIfMissing('test@example.com', 'Test User');
        $this->seedUserIfMissing('jane.staff@example.com', 'Jane Staff');
        $this->call(RubenAdminSeeder::class);

        $this->call(CustomerSeeder::class);
        $this->call(DemoCatalogSeeder::class);
        $this->call(DemoWorkspaceUiSeeder::class);
    }

    private function seedUserIfMissing(string $email, string $name): void
    {
        $user = User::withTrashed()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }

        if ($user->wasRecentlyCreated) {
            $user->forceFill([
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ])->save();
        }
    }
}
