<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Roles first: the sample group hands every account an office, and that only works once the
     * offices exist.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(PegSeeder::class);
    }
}
