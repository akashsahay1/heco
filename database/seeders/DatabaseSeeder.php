<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            RegionSeeder::class,
            SystemListSeeder::class,
            AiPromptSeeder::class,
            SettingSeeder::class,
            CurrencySeeder::class,
            TestDataSeeder::class,
        ]);
    }
}
