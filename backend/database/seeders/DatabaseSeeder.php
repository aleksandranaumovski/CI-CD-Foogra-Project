<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Note: deliberately *not* using WithoutModelEvents — slug generation, derived
 * review scores and the cached rating aggregates all hang off model events.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            RestaurantSeeder::class,
            ReviewSeeder::class,
            BookingSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo logins — password for all accounts: password');
        $this->command?->table(
            ['Role', 'Email'],
            [
                ['Admin', 'admin@foogra.test'],
                ['Owner', 'owner@foogra.test'],
                ['Owner', 'owner2@foogra.test'],
                ['Customer', 'customer@foogra.test'],
            ]
        );
    }
}
