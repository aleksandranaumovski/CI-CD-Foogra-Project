<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Three known logins (password: `password`) so the demo can be driven
 * immediately, plus a pool of customers to attach reviews and bookings to.
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $accounts = [
            // The template's avatar*.jpg files are grey "IMAGE PLACEHOLDER"
            // panels; avatar_*.svg are its actual illustrated portraits.
            ['name' => 'Ada Admin', 'email' => 'admin@foogra.test', 'role' => UserRole::Admin, 'avatar' => 'img/avatar_1.svg'],
            ['name' => 'Olivia Owner', 'email' => 'owner@foogra.test', 'role' => UserRole::Owner, 'avatar' => 'img/avatar_2.svg'],
            ['name' => 'Marco Rossi', 'email' => 'owner2@foogra.test', 'role' => UserRole::Owner, 'avatar' => 'img/avatar_3.svg'],
            ['name' => 'Chris Customer', 'email' => 'customer@foogra.test', 'role' => UserRole::Customer, 'avatar' => 'img/avatar_1.svg'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => $account['role'],
                    'avatar_path' => $account['avatar'],
                    'email_verified_at' => now(),
                    'phone' => '+44 20 7946 0000',
                ]
            );
        }

        // Reviewers and diners for the generated content.
        User::factory()->count(40)->customer()->create();
    }
}
