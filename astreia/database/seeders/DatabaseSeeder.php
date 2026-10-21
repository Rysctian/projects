<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AstreiaDemoSeeder::class);
        $this->call(CoreDemoSeeder::class);
    }
}
