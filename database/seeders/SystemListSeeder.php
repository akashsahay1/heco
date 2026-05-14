<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemList;

/**
 * Seeds the master dropdown lists used across the platform. Every entry
 * carries a description so admins, SPs, and (where surfaced) travellers
 * understand what each option means — no more "Cat A vs Cat B vs Cat C"
 * guessing games.
 *
 * Idempotent — updateOrCreate on (list_type, name). Re-running refreshes
 * descriptions without disturbing sort_order / is_active.
 */
class SystemListSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'service_type' => [
                ['Accommodation', 'A place to stay — hotel, lodge, homestay, camping. Priced per night per category and occupancy.'],
                ['Transport',     'Vehicle service to move travellers between locations. Priced per km × vehicle type, or per day for dedicated cars.'],
                ['Guide',         'Local or specialist guide accompanying the traveller. Priced per day; rates vary by guide language and expertise.'],
                ['Activity',      'Discrete experiences (rafting, cooking class, village walk). Priced per person, per group, or per day.'],
                ['Meals',         'Standalone meals or meal plans not bundled into an experience. BB / HB / FB plans, see meal_plan list.'],
                ['Other',         'Anything outside the above — permits, equipment rental, photography fees, ferry tickets, etc.'],
            ],
            'accommodation_category' => [
                ['Cat A - Luxury',        'Premium hotels, 4-5 star, ensuite bathrooms, AC, room service, full amenities. Typically ₹6000+/night.'],
                ['Cat B - Comfort',       'Mid-upper hotels or boutique lodges, 3-4 star, ensuite, good service. ₹3000-6000/night.'],
                ['Cat C - Standard',      'Standard hotels or quality homestays, private bathroom, basic amenities, clean and reliable. ₹1500-3000/night.'],
                ['Cat D - Basic/Homestay','Authentic village homestays or simple lodges, shared or private bathroom, local hospitality. ₹500-1500/night.'],
            ],
            'vehicle_type' => [
                ['SUV (Innova/Crysta)',   '7-seater comfortable SUV. Best for groups of 4-6 with luggage on mountain roads. Standard for HECO trips.'],
                ['SUV (Bolero/Scorpio)',  'Rugged 4×4 SUV, 6-7 seats. Better for rough terrain than Innova but less comfortable. ~80% Innova rate.'],
                ['Sedan',                 '4-5 seater car. Comfortable on tarmac but not ideal for steep/rough mountain roads.'],
                ['Tempo Traveller',       '12-17 seater mini-bus. For larger groups; cheaper per head but less manoeuvrable on hairpin bends.'],
                ['Bus',                   '30-50 seater coach. Long-distance only; not used inside mountain valleys.'],
                ['Bike',                  'Royal Enfield or similar. Self-drive for adventure travellers; HECO usually supplies a chase vehicle.'],
            ],
            'activity_type' => [
                ['Trek',             'Multi-hour or multi-day walk through mountain terrain. Difficulty varies — see difficulty_level on the experience.'],
                ['Cultural Immersion','Village stay + traditions: local cuisine, rituals, crafts, language. Slow pace, deep contact.'],
                ['Nature Walk',      'Easy guided walk through forests, meadows, or river valleys. Suitable for all fitness levels.'],
                ['Wildlife',         'Bird watching, mammal spotting, wildlife sanctuary visits. Requires patience + early starts.'],
                ['Spiritual',        'Meditation retreats, monastery visits, pilgrim sites. Mix of guided + solo time.'],
                ['Adventure Sports', 'Rafting, paragliding, rock climbing, skiing. Requires gear + qualified instructors.'],
                ['Photography',      'Guided photo tour with focus on landscapes, people, or wildlife. Suits dedicated photographers.'],
                ['Cooking Class',    'Hands-on local cuisine session with a host family or chef.'],
                ['Craft Workshop',   'Traditional crafts — weaving, pottery, paper-making, woodwork. Half-day to multi-day.'],
                ['Farm Visit',       'Working farm or orchard day — meet farmers, see production, share a meal.'],
            ],
            'experience_type' => [
                ['Trek',              'An experience whose primary activity is trekking (any difficulty).'],
                ['Cultural Immersion','An experience whose primary purpose is cultural exchange with a local community.'],
                ['Spiritual',         'Retreats, meditation, monastery stays.'],
                ['Nature',            'Nature-focused experiences — walks, river trips, forest stays.'],
                ['Adventure',         'Adventure sports + adrenaline activities.'],
                ['Wellness',          'Yoga, ayurveda, healing, mountain retreats.'],
                ['Culinary',          'Food-focused — cooking, foraging, farm-to-table.'],
                ['Photography',       'Photography-led experiences.'],
                ['Wildlife',          'Wildlife-spotting or sanctuary-based experiences.'],
                ['Volunteering',      'Hands-on contribution to a local project (planting, building, teaching).'],
            ],
            'payment_mode' => [
                ['Bank Transfer', 'NEFT / IMPS / RTGS / SWIFT for international. Record UTR in notes.'],
                ['UPI',           'GPay / PhonePe / Paytm / direct UPI. Record the UPI ref ID in notes.'],
                ['Cash',          'Hand-delivered cash payment. Always record a receipt number.'],
                ['Remitly',       'For international travellers — Remitly transfer, INR landing in HECO account.'],
                ['Wise',          'Wise (formerly TransferWise) — multi-currency, fast for European/US travellers.'],
                ['PayPal',        'PayPal — convenient for international but higher fees. Use only for small amounts.'],
                ['Other',         'Any other channel — record details in notes.'],
            ],

            // ─── new types for sp_pricing.unit and sp_pricing.meal_plan ───
            'occupancy_unit' => [
                ['per person',    'Rate is per individual person. Common for activities, guides, and meal-only services.'],
                ['per single',    'Single occupancy — one person per room. Highest per-person rate.'],
                ['per double',    'Double occupancy — two people sharing a room. Standard for couples.'],
                ['per triple',    'Triple occupancy — three people sharing a room. Common for families / friend groups.'],
                ['per quad',      'Quad occupancy — four people sharing a room/dorm. Family rooms or dorms.'],
                ['per room',      'Flat rate per room regardless of occupancy. Used by some lodges.'],
                ['per night',     'Per-night rate (works alongside room/person basis).'],
                ['per day',       'Per-day rate — used for guides, dedicated vehicles, day-long activities.'],
                ['per km',        'Per-kilometre rate — used for transport billed by distance.'],
                ['per group',     'Flat rate for the whole group regardless of headcount. Used for private activities.'],
            ],
            'meal_plan' => [
                ['No meals',         'No meals included. Traveller pays for food separately.'],
                ['BB - Breakfast only', 'Bed and Breakfast. Only morning meal included.'],
                ['HB - Half Board',  'Breakfast and one main meal (usually dinner). Lunch is on the traveller.'],
                ['FB - Full Board',  'All three meals — breakfast, lunch, dinner.'],
                ['MAP - Modified American Plan', 'Breakfast + lunch OR dinner (traveller picks).'],
                ['AP - All Inclusive', 'All meals + snacks + non-alcoholic drinks included.'],
            ],

            // ─── room categories for hotel-style accommodation inventory ───
            'room_category' => [
                ['Single Room',    'One bed for one person. Smallest, cheapest. Best for solo travellers.'],
                ['Double Room',    'One double bed for two people sharing. Standard hotel default.'],
                ['Twin Sharing',   'Two separate single beds — two people sharing the room but not the bed. Friends / colleagues.'],
                ['Triple Room',    'Three beds in one room (or one double + one single). For families / groups of 3.'],
                ['Quad Room',      'Four beds in one room. Family rooms or small group dorms.'],
                ['Family Suite',   'Multi-room suite — living area + 1-2 bedrooms. For families with kids.'],
                ['Dormitory Bed',  'One bed in a shared dormitory (4-12 beds). Cheapest option, suits solo backpackers.'],
                ['Deluxe Room',    'Upgraded room — better view, larger size, premium amenities.'],
                ['Suite',          'Full suite — separate bedroom + living area, top-tier service.'],
                ['Camping Tent',   'Pre-pitched tent for 2 — sleeping bags + camp kitchen access. For trek-based stays.'],
            ],
        ];

        foreach ($lists as $type => $items) {
            foreach ($items as $index => $row) {
                [$name, $description] = $row;
                SystemList::updateOrCreate(
                    ['list_type' => $type, 'name' => $name],
                    [
                        'sort_order'  => $index,
                        'is_active'   => true,
                        'description' => $description,
                    ]
                );
            }
        }
    }
}
