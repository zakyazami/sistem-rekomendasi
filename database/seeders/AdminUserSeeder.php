<?php

namespace Database\Seeders;

use App\Domain\Users\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $settings = config('seeding.admin');
        $name = trim((string) ($settings['name'] ?? ''));
        $email = trim((string) ($settings['email'] ?? ''));
        $password = $settings['password'] ?? null;
        $forcePassword = (bool) ($settings['force_password'] ?? false);

        if ($name === '' || $email === '') {
            $this->command?->info('PERINGATAN: Admin tidak dibuat karena nama dan email seeder wajib diisi.');

            return;
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin instanceof User) {
            $updates = [
                'name' => $name,
                'role' => UserRole::Admin,
                'email_verified_at' => $admin->email_verified_at ?? now(),
            ];

            if ($forcePassword && filled($password)) {
                $updates['password'] = $password;
            }

            $admin->fill($updates)->save();
            $this->command?->info('Admin sudah ada; data identitas diselaraskan.');

            return;
        }

        if (blank($password)) {
            if (app()->environment('production')) {
                $this->command?->info('PERINGATAN: Admin tidak dibuat karena SEED_ADMIN_PASSWORD wajib pada production.');

                return;
            }

            $password = 'password';
            $this->command?->info('PERINGATAN: Admin memakai password fallback lokal. Segera ganti setelah login.');
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->command?->info('Admin berhasil dibuat.');
    }
}
