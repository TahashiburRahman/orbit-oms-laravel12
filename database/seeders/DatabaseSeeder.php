<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command?->info('Demo data is opt-in. Use php artisan oms:demo to seed with a hidden password prompt.');
    }
}
