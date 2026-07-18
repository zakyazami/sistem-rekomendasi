<?php

namespace Database\Seeders;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_ADMIN_PASSWORD');

        if (blank($password) && app()->environment('production')) {
            $this->command?->warn('Admin tidak dibuat: tetapkan SEED_ADMIN_PASSWORD di environment produksi.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@tokobarokah.local')],
            [
                'name' => env('SEED_ADMIN_NAME', 'Administrator Toko Barokah'),
                'password' => $password ?: 'password',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
