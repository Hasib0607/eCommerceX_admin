<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminVisitor;

class AdminVisitorSeeder extends Seeder
{
    public function run()
    {
        AdminVisitor::factory()->count(rand(150, 200))->create();
    }
}
