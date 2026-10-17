<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Demo data so the dashboard is not empty on a fresh install. */
    public function run(): void
    {
        Order::factory()->count(4)->paid()->create();
        Order::factory()->count(2)->failed()->create();
        Order::factory()->count(2)->create();
    }
}
