<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Announcement Categories
            [
                'name' => 'News',
                'description' => 'Latest news and updates',
                'type' => 'announcement'
            ],
            [
                'name' => 'Updates',
                'description' => 'System and service updates',
                'type' => 'announcement'
            ],
            [
                'name' => 'Alerts',
                'description' => 'Important alerts and notifications',
                'type' => 'announcement'
            ],

            // Opportunity Categories
            [
                'name' => 'Jobs',
                'description' => 'Job opportunities and vacancies',
                'type' => 'opportunity'
            ],
            [
                'name' => 'Contracts',
                'description' => 'Contract opportunities',
                'type' => 'opportunity'
            ],
            [
                'name' => 'Tenders',
                'description' => 'Tender announcements',
                'type' => 'opportunity'
            ],
            [
                'name' => 'Grants',
                'description' => 'Grant opportunities',
                'type' => 'opportunity'
            ],

            // Event Categories
            [
                'name' => 'Meetings',
                'description' => 'Community meetings and gatherings',
                'type' => 'event'
            ],
            [
                'name' => 'Workshops',
                'description' => 'Training and skill development workshops',
                'type' => 'event'
            ],
            [
                'name' => 'Ceremonies',
                'description' => 'Official ceremonies and celebrations',
                'type' => 'event'
            ],
            [
                'name' => 'Community Events',
                'description' => 'Local community events and activities',
                'type' => 'event'
            ]
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
