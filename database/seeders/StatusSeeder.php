<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Planned', 'description' => 'Planned or scheduled'],
            ['name' => 'Ongoing', 'description' => 'Work in progress'],
            ['name' => 'Completed', 'description' => 'Work completed'],
            ['name' => 'On Hold', 'description' => 'Temporarily paused'],
            ['name' => 'Cancelled', 'description' => 'Work cancelled'],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(['name' => $status['name']], $status);
        }
    }
}
