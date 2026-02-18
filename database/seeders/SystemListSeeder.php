<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemList;

class SystemListSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'service_type' => ['Accommodation', 'Transport', 'Guide', 'Activity', 'Meals', 'Other'],
            'accommodation_category' => ['Cat A - Luxury', 'Cat B - Comfort', 'Cat C - Standard', 'Cat D - Basic/Homestay'],
            'vehicle_type' => ['SUV (Innova/Crysta)', 'SUV (Bolero/Scorpio)', 'Sedan', 'Tempo Traveller', 'Bus', 'Bike'],
            'activity_type' => ['Trek', 'Cultural Immersion', 'Nature Walk', 'Wildlife', 'Spiritual', 'Adventure Sports', 'Photography', 'Cooking Class', 'Craft Workshop', 'Farm Visit'],
            'experience_type' => ['Trek', 'Cultural Immersion', 'Spiritual', 'Nature', 'Adventure', 'Wellness', 'Culinary', 'Photography', 'Wildlife', 'Volunteering'],
            'payment_mode' => ['Bank Transfer', 'UPI', 'Cash', 'Remitly', 'Wise', 'PayPal', 'Other'],
        ];

        foreach ($lists as $type => $items) {
            foreach ($items as $index => $name) {
                SystemList::firstOrCreate(
                    ['list_type' => $type, 'name' => $name],
                    ['sort_order' => $index, 'is_active' => true]
                );
            }
        }
    }
}
