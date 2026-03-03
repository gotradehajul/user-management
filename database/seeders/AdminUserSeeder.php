<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed initial admin users.
     */
    public function run(): void
    {
        $name = 'System Admin';
        $email = 'admin@example.com';

        $admin = User::query()->where('email', $email)->first();

        if (! $admin) {
            $plainPassword = Str::password(16);

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'role' => 'admin',
                'active' => true,
                'email_verified_at' => now(),
            ]);

            if ($this->command) {
                $this->command->warn('Initial admin user created.');
                $this->command->line("Email: {$email}");
                $this->command->line("Password: {$plainPassword}");
            }

            return;
        }

        $admin->forceFill([
            'name' => $name,
            'role' => 'admin',
            'active' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
    }
}
