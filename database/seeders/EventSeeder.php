<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        $events = [
            [
                'name' => 'ICT Foundation Annual Summit 2026',
                'slug' => 'ict-foundation-annual-summit-2026',
                'description' => 'A premier gathering of technology leaders, innovators, and policymakers exploring the future of digital transformation in Nepal.',
                'event_date' => '2026-09-15',
                'start_datetime' => '2026-09-15 09:00:00',
                'end_datetime' => '2026-09-16 17:00:00',
                'registration_open_at' => '2026-06-01 00:00:00',
                'registration_close_at' => '2026-09-10 23:59:59',
                'venue' => 'Hotel Yak & Yeti, Durbar Marg, Kathmandu',
                'contact_info' => 'info@ictfoundation.org.np | +977-1-4XXXXXX',
                'meal_types' => ['lunch', 'dinner'],
                'max_capacity' => 500,
                'status' => 'published',
            ],
            [
                'name' => 'Cybersecurity Workshop 2026',
                'slug' => 'cybersecurity-workshop-2026',
                'description' => 'Hands-on workshop covering threat detection, incident response, and security best practices for enterprise IT teams.',
                'event_date' => '2026-08-20',
                'start_datetime' => '2026-08-20 10:00:00',
                'end_datetime' => '2026-08-20 17:00:00',
                'registration_open_at' => '2026-06-01 00:00:00',
                'registration_close_at' => '2026-08-18 23:59:59',
                'venue' => 'Innovation Hub, Baluwatar, Kathmandu',
                'contact_info' => 'workshop@ictfoundation.org.np',
                'meal_types' => ['lunch'],
                'max_capacity' => 100,
                'status' => 'published',
            ],
            [
                'name' => 'AI & Data Science Meetup 2026',
                'slug' => 'ai-data-science-meetup-2026',
                'description' => 'Community-driven meetup featuring talks on machine learning, LLMs, and data engineering.',
                'event_date' => '2026-07-10',
                'start_datetime' => '2026-07-10 14:00:00',
                'end_datetime' => '2026-07-10 18:00:00',
                'registration_open_at' => '2026-06-01 00:00:00',
                'registration_close_at' => '2026-07-09 23:59:59',
                'venue' => 'Kings College, Babarmahal, Kathmandu',
                'contact_info' => 'meetup@ictfoundation.org.np',
                'meal_types' => ['dinner'],
                'max_capacity' => 200,
                'status' => 'published',
            ],
        ];

        foreach ($events as $data) {
            $data['created_by'] = $admin?->id;
            Event::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
