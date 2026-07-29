<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            Lq45Seeder::class,
            SubscriptionPlanSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
