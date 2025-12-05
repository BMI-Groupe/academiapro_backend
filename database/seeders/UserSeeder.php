<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * DEPRECATED: This seeder is no longer used.
     * All user creation has been moved to InitialDataSeeder.
     * 
     * Please use InitialDataSeeder instead.
     */
    public function run(): void
    {
        // This seeder is deprecated.
        // All users are now created in InitialDataSeeder.
        $this->command->warn('UserSeeder is deprecated. Using InitialDataSeeder instead.');
        $this->call(InitialDataSeeder::class);
    }
}
