<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultRoleUsersSeeder extends Seeder
{
    /**
     * Seed default non-admin role users.
     */
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'Default Manager',
                'email' => 'manager@example.com',
                'role' => 'manager',
            ],
            [
                'name' => 'Default User',
                'email' => 'user@example.com',
                'role' => 'user',
            ],
        ];

        foreach ($defaults as $defaultUser) {
            $existing = User::query()->where('email', $defaultUser['email'])->first();

            if (! $existing) {
                $plainPassword = Str::password(16);

                User::create([
                    'name' => $defaultUser['name'],
                    'email' => $defaultUser['email'],
                    'password' => Hash::make($plainPassword),
                    'role' => $defaultUser['role'],
                    'active' => true,
                    'email_verified_at' => now(),
                ]);

                if ($this->command) {
                    $this->command->warn("Initial {$defaultUser['role']} user created.");
                    $this->command->line("Email: {$defaultUser['email']}");
                    $this->command->line("Password: {$plainPassword}");
                }

                continue;
            }

            $existing->forceFill([
                'name' => $defaultUser['name'],
                'role' => $defaultUser['role'],
                'active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();
        }
    }
}
