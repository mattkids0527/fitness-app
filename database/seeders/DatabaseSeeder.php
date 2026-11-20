<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 執行 php artisan db:seed（或 migrate:fresh --seed）時的資料：目前只有本地開發帳號。
     */
    public function run(): void
    {
        $this->call(DevAccountSeeder::class);
    }
}
