<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed one admin user with a randomly generated password.
     *
     * The password is printed to the console at seed time only - it is
     * NOT stored anywhere, not logged, and not meant to be production
     * ready. Whoever runs this seeder must capture that output and
     * change the password immediately after first login (there is no
     * Phase 1 "change password" UI beyond the standard reset-password
     * API endpoint, which works fine for this purpose).
     */
    public function run(): void
    {
        $email = 'daviescareroute@gmail.com';

        if (User::where('email', $email)->exists()) {
            $this->command?->warn("Admin user {$email} already exists - skipping.");

            return;
        }

        $password = Str::password(20);

        User::create([
            'name' => 'Unwrap24 Admin',
            'email' => $email,
            'password' => $password,
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->command?->warn("Seeded admin user {$email} with a random password.");
        $this->command?->warn("Generated password (change this immediately via /api/auth/forgot-password): {$password}");
    }
}
