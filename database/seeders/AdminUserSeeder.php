<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Creates the first admin account so there's a way to log in at all
     * before any real user exists. Generates a random password and
     * prints it once to the console — nothing generated here is stored
     * in code, change it after first login.
     */
    public function run(): void
    {
        if (User::query()->where('is_admin', true)->exists()) {
            $this->command?->info('An admin user already exists — skipping.');

            return;
        }

        $email = 'admin@idxinvest.test';
        $password = Str::password(16);

        User::query()->create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => $password, // hashed automatically by User's 'password' => 'hashed' cast
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->warn("Admin created — email: {$email} / password: {$password} (change this after first login)");
    }
}
