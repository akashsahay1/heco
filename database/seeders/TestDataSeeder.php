<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ServiceProvider;
use App\Models\Experience;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripDayExperience;
use App\Models\TripSelectedExperience;
use App\Models\TripRegion;
use App\Models\Lead;
use App\Models\Region;
use App\Models\RegenerativeProject;
use App\Models\PdfTemplate;
use App\Models\TravellerPayment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch the admin user for approved_by references
        $admin = User::where('email', 'admin@hecoapp.com')->first();
        $adminId = $admin?->id;

        // ─────────────────────────────────────────────────────────
        // 1. SERVICE PROVIDER USERS + SERVICE PROVIDERS (12 total)
        // ─────────────────────────────────────────────────────────

        // --- HRP Users & Providers (4) ---

        $hrpSonam = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Sonam Wangchuk',
                'email' => 'sonam.wangchuk@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9419812345',
            ],
            providerAttrs: [
                'provider_type' => 'hrp',
                'name' => 'Sonam Wangchuk',
                'contact_person' => 'Sonam Wangchuk',
                'email' => 'sonam.wangchuk@hecoapp.com',
                'phone_1' => '+91-9419812345',
                'region_slug' => 'ladakh',
                'address' => 'Fort Road, Leh, Ladakh 194101, India',
                'services_offered' => ['Accommodation', 'Transport', 'Guide'],
                'notes' => 'Experienced HRP covering entire Ladakh region. Runs a network of homestays and transport providers.',
                'status' => 'approved',
                'bank_name' => 'J&K Bank',
                'bank_ifsc' => 'JAKA0LEHXXX',
                'bank_account_name' => 'Sonam Wangchuk',
                'bank_account_number' => '0123456789012345',
            ],
            adminId: $adminId,
        );

        $hrpTenzin = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Tenzin Norbu',
                'email' => 'tenzin.norbu@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9418234567',
            ],
            providerAttrs: [
                'provider_type' => 'hrp',
                'name' => 'Tenzin Norbu',
                'contact_person' => 'Tenzin Norbu',
                'email' => 'tenzin.norbu@hecoapp.com',
                'phone_1' => '+91-9418234567',
                'region_slug' => 'spiti-valley',
                'address' => 'Main Bazaar, Kaza, Spiti Valley 172114, India',
                'services_offered' => ['Accommodation', 'Transport', 'Guide', 'Activity'],
                'notes' => 'HRP for Spiti Valley. Deep knowledge of monastery circuits and snow leopard trails.',
                'status' => 'approved',
                'bank_name' => 'State Bank of India',
                'bank_ifsc' => 'SBIN0006842',
                'bank_account_name' => 'Tenzin Norbu',
                'bank_account_number' => '3456789012345678',
            ],
            adminId: $adminId,
        );

        $hrpPemba = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Pemba Sherpa',
                'email' => 'pemba.sherpa@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+977-9841123456',
            ],
            providerAttrs: [
                'provider_type' => 'hrp',
                'name' => 'Pemba Sherpa',
                'contact_person' => 'Pemba Sherpa',
                'email' => 'pemba.sherpa@hecoapp.com',
                'phone_1' => '+977-9841123456',
                'region_slug' => 'everest-region',
                'address' => 'Namche Bazaar, Solukhumbu, Nepal',
                'services_offered' => ['Accommodation', 'Transport', 'Guide', 'Activity'],
                'notes' => 'Veteran HRP in Everest Region. Third-generation Sherpa guide with extensive high-altitude experience.',
                'status' => 'approved',
                'bank_name' => 'Nepal Investment Bank',
                'bank_ifsc' => null,
                'bank_account_name' => 'Pemba Sherpa',
                'bank_account_number' => 'NIB-0987654321',
            ],
            adminId: $adminId,
        );

        $hrpCarlos = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Carlos Gutierrez',
                'email' => 'carlos.gutierrez@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+51-984567890',
            ],
            providerAttrs: [
                'provider_type' => 'hrp',
                'name' => 'Carlos Gutierrez',
                'contact_person' => 'Carlos Gutierrez',
                'email' => 'carlos.gutierrez@hecoapp.com',
                'phone_1' => '+51-984567890',
                'region_slug' => 'sacred-valley',
                'address' => 'Calle del Medio 114, Ollantaytambo, Sacred Valley, Peru',
                'services_offered' => ['Accommodation', 'Transport', 'Guide'],
                'notes' => 'Applying as HRP for Sacred Valley region. Runs community tourism initiatives with Quechua communities.',
                'status' => 'pending',
                'bank_name' => null,
                'bank_ifsc' => null,
                'bank_account_name' => null,
                'bank_account_number' => null,
            ],
            adminId: null, // pending, not yet approved
        );

        // --- HLH Users & Providers (4) ---

        $hlhTsering = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Tsering Angmo',
                'email' => 'tsering.angmo@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9419876543',
            ],
            providerAttrs: [
                'provider_type' => 'hlh',
                'name' => 'Tsering Angmo',
                'contact_person' => 'Tsering Angmo',
                'email' => 'tsering.angmo@hecoapp.com',
                'phone_1' => '+91-9419876543',
                'region_slug' => 'ladakh',
                'address' => 'Village Tar, Saboo Road, Leh, Ladakh 194101, India',
                'accommodation_categories' => ['Cat D - Basic/Homestay'],
                'services_offered' => ['Accommodation', 'Meals'],
                'notes' => 'Runs a traditional Ladakhi homestay in Tar village. Known for authentic cultural experiences and home-cooked meals.',
                'status' => 'approved',
                'bank_name' => 'J&K Bank',
                'bank_ifsc' => 'JAKA0LEHXXX',
                'bank_account_name' => 'Tsering Angmo',
                'bank_account_number' => '1122334455667788',
            ],
            adminId: $adminId,
        );

        $hlhDawa = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Dawa Dolma',
                'email' => 'dawa.dolma@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9418765432',
            ],
            providerAttrs: [
                'provider_type' => 'hlh',
                'name' => 'Dawa Dolma',
                'contact_person' => 'Dawa Dolma',
                'email' => 'dawa.dolma@hecoapp.com',
                'phone_1' => '+91-9418765432',
                'region_slug' => 'tirthan-valley',
                'address' => 'Gushaini Village, Tirthan Valley, Kullu 175123, India',
                'accommodation_categories' => ['Cat C - Standard', 'Cat D - Basic/Homestay'],
                'services_offered' => ['Accommodation', 'Meals', 'Guide'],
                'notes' => 'Homestay host in Tirthan Valley. Expert on Great Himalayan National Park trails and local biodiversity.',
                'status' => 'approved',
                'bank_name' => 'Punjab National Bank',
                'bank_ifsc' => 'PUNB0678900',
                'bank_account_name' => 'Dawa Dolma',
                'bank_account_number' => '6789012345678901',
            ],
            adminId: $adminId,
        );

        $hlhMaya = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Maya Tamang',
                'email' => 'maya.tamang@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+977-9812345678',
            ],
            providerAttrs: [
                'provider_type' => 'hlh',
                'name' => 'Maya Tamang',
                'contact_person' => 'Maya Tamang',
                'email' => 'maya.tamang@hecoapp.com',
                'phone_1' => '+977-9812345678',
                'region_slug' => 'annapurna',
                'address' => 'Ghandruk Village, Annapurna, Kaski, Nepal',
                'accommodation_categories' => ['Cat D - Basic/Homestay'],
                'services_offered' => ['Accommodation', 'Meals'],
                'notes' => 'Gurung community homestay host in Ghandruk. Offers authentic Gurung cultural immersion and traditional cooking.',
                'status' => 'approved',
                'bank_name' => 'Nabil Bank',
                'bank_ifsc' => null,
                'bank_account_name' => 'Maya Tamang',
                'bank_account_number' => 'NABIL-1234567890',
            ],
            adminId: $adminId,
        );

        $hlhKarma = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Karma Lhamo',
                'email' => 'karma.lhamo@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+975-17234567',
            ],
            providerAttrs: [
                'provider_type' => 'hlh',
                'name' => 'Karma Lhamo',
                'contact_person' => 'Karma Lhamo',
                'email' => 'karma.lhamo@hecoapp.com',
                'phone_1' => '+975-17234567',
                'region_slug' => 'paro-valley',
                'address' => 'Bondey Village, Paro Valley, Bhutan',
                'accommodation_categories' => ['Cat C - Standard', 'Cat D - Basic/Homestay'],
                'services_offered' => ['Accommodation', 'Meals', 'Guide'],
                'notes' => 'Traditional Bhutanese farmhouse host in Paro Valley. Offers guided visits to Tiger\'s Nest and local dzongs.',
                'status' => 'approved',
                'bank_name' => 'Bank of Bhutan',
                'bank_ifsc' => null,
                'bank_account_name' => 'Karma Lhamo',
                'bank_account_number' => 'BOB-9876543210',
            ],
            adminId: $adminId,
        );

        // --- OSP Users & Providers (4) ---

        $ospHimalayan = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Rajesh Thakur',
                'email' => 'himalayan.adventures@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9418111222',
            ],
            providerAttrs: [
                'provider_type' => 'osp',
                'name' => 'Himalayan Adventures Pvt Ltd',
                'contact_person' => 'Rajesh Thakur',
                'email' => 'himalayan.adventures@hecoapp.com',
                'phone_1' => '+91-9418111222',
                'phone_2' => '+91-1902252345',
                'region_slug' => 'tirthan-valley',
                'address' => 'Aut, Mandi-Kullu Highway, Himachal Pradesh 175126, India',
                'vehicle_types' => ['SUV (Innova/Crysta)', 'SUV (Bolero/Scorpio)', 'Tempo Traveller'],
                'services_offered' => ['Transport'],
                'notes' => 'Reliable transport provider with fleet of well-maintained vehicles covering Kullu, Manali, Tirthan, and Spiti routes.',
                'status' => 'approved',
                'bank_name' => 'HDFC Bank',
                'bank_ifsc' => 'HDFC0001234',
                'bank_account_name' => 'Himalayan Adventures Pvt Ltd',
                'bank_account_number' => 'HDFC-5566778899',
                'upi' => 'himadv@hdfcbank',
            ],
            adminId: $adminId,
        );

        $ospPeak = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Dorje Namgyal',
                'email' => 'peak.trekking@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+91-9419333444',
            ],
            providerAttrs: [
                'provider_type' => 'osp',
                'name' => 'Peak Trekking Co.',
                'contact_person' => 'Dorje Namgyal',
                'email' => 'peak.trekking@hecoapp.com',
                'phone_1' => '+91-9419333444',
                'region_slug' => 'ladakh',
                'address' => 'Changspa Road, Leh, Ladakh 194101, India',
                'activity_types' => ['Trek', 'Adventure Sports', 'Nature Walk'],
                'services_offered' => ['Guide', 'Activity'],
                'notes' => 'Professional trekking and guide services across Ladakh and Zanskar. Certified mountaineering guides on staff.',
                'status' => 'approved',
                'bank_name' => 'J&K Bank',
                'bank_ifsc' => 'JAKA0LEHXXX',
                'bank_account_name' => 'Peak Trekking Co',
                'bank_account_number' => 'JKB-4455667788',
            ],
            adminId: $adminId,
        );

        $ospNepal = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Binod Rai',
                'email' => 'nepal.journeys@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+977-9851234567',
            ],
            providerAttrs: [
                'provider_type' => 'osp',
                'name' => 'Nepal Journeys',
                'contact_person' => 'Binod Rai',
                'email' => 'nepal.journeys@hecoapp.com',
                'phone_1' => '+977-9851234567',
                'phone_2' => '+977-01-4567890',
                'region_slug' => 'everest-region',
                'address' => 'Thamel, Kathmandu, Nepal',
                'vehicle_types' => ['SUV (Innova/Crysta)', 'Bus'],
                'activity_types' => ['Trek', 'Cultural Immersion', 'Adventure Sports'],
                'services_offered' => ['Transport', 'Activity'],
                'notes' => 'Full-service Nepal operator covering transport and trekking activities for Everest, Annapurna, and Langtang regions.',
                'status' => 'approved',
                'bank_name' => 'Standard Chartered Nepal',
                'bank_ifsc' => null,
                'bank_account_name' => 'Nepal Journeys Pvt Ltd',
                'bank_account_number' => 'SCN-1122334455',
            ],
            adminId: $adminId,
        );

        $ospAndean = $this->createProviderWithUser(
            userAttrs: [
                'full_name' => 'Maria Quispe',
                'email' => 'andean.trails@hecoapp.com',
                'user_role' => 'provider',
                'mobile' => '+51-984111222',
            ],
            providerAttrs: [
                'provider_type' => 'osp',
                'name' => 'Andean Trails',
                'contact_person' => 'Maria Quispe',
                'email' => 'andean.trails@hecoapp.com',
                'phone_1' => '+51-984111222',
                'region_slug' => 'sacred-valley',
                'address' => 'Plaza de Armas, Cusco, Peru',
                'vehicle_types' => ['SUV (Innova/Crysta)', 'Bus'],
                'services_offered' => ['Transport'],
                'notes' => 'Applying as transport provider for Sacred Valley and Cusco region. Pending verification of fleet and insurance.',
                'status' => 'pending',
                'bank_name' => null,
                'bank_ifsc' => null,
                'bank_account_name' => null,
                'bank_account_number' => null,
            ],
            adminId: null, // pending, not yet approved
        );

        // ─────────────────────────────────────────────────────────
        // 2. EXPERIENCES (20 records)
        // ─────────────────────────────────────────────────────────

        // We need HLH IDs for experiences. For regions without a direct HLH,
        // we assign the nearest available HLH.
        $hlhTseringId = $hlhTsering->id;  // Ladakh
        $hlhDawaId    = $hlhDawa->id;     // Tirthan Valley
        $hlhMayaId    = $hlhMaya->id;     // Annapurna
        $hlhKarmaId   = $hlhKarma->id;    // Paro Valley

        // Region ID lookups
        $regionIds = [];
        $regionSlugs = [
            'ladakh', 'tirthan-valley', 'spiti-valley', 'kinnaur', 'zanskar',
            'sikkim', 'kumaon', 'everest-region', 'annapurna', 'langtang',
            'paro-valley', 'bumthang', 'sacred-valley',
        ];
        foreach ($regionSlugs as $slug) {
            $region = Region::where('slug', $slug)->first();
            if ($region) {
                $regionIds[$slug] = $region->id;
            }
        }

        $experiences = [
            // --- India (10) ---
            [
                'name' => 'Village Experience in Tar',
                'slug' => 'village-experience-in-tar',
                'hlh_id' => $hlhTseringId,
                'region_id' => $regionIds['ladakh'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'Immerse yourself in daily life at Tar village near Leh. Stay with a Ladakhi family, learn traditional cooking, and experience Buddhist rituals in an authentic mountain hamlet.',
                'long_description' => 'Tar village sits on the outskirts of Leh, offering an intimate window into Ladakhi culture without venturing far from the city. Your host family will introduce you to traditional butter tea making, momos preparation, and the rhythms of life in a high-altitude desert community. Visit the local gompa for morning prayers and walk through apricot orchards as the sun sets over the Stok range.',
                'duration_type' => 'multi_day',
                'duration_days' => 3,
                'duration_nights' => 2,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 8500.00,
                'cost_accommodation' => 3000.00,
                'cost_logistics' => 1500.00,
                'cost_guide' => 2000.00,
                'cost_activities' => 1500.00,
                'group_size_min' => 2,
                'group_size_max' => 8,
                'start_latitude' => 34.1700,
                'start_longitude' => 77.5850,
                'altitude_min' => 3400,
                'altitude_max' => 3600,
                'best_seasons' => ['Summer', 'Autumn'],
                'available_months' => [5, 6, 7, 8, 9, 10],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pangong Lake Expedition',
                'slug' => 'pangong-lake-expedition',
                'hlh_id' => $hlhTseringId,
                'region_id' => $regionIds['ladakh'] ?? null,
                'type' => 'Trek',
                'short_description' => 'A challenging single-day trek through the Chang La pass leading to the mesmerizing Pangong Tso lake. Witness the lake\'s legendary colour shifts from azure to turquoise.',
                'long_description' => 'Starting from Leh at dawn, this expedition crosses the Chang La pass at 5,360m before descending to the shores of Pangong Lake. The journey passes through stark, otherworldly landscapes of the Changthang plateau. At the lake, you will have time to absorb the stunning panorama where the water shifts through impossible shades of blue against barren brown mountains.',
                'duration_type' => 'single_day',
                'duration_days' => 1,
                'duration_nights' => 0,
                'duration_hours' => null,
                'difficulty_level' => 'challenging',
                'base_cost_per_person' => 4500.00,
                'cost_accommodation' => 0,
                'cost_logistics' => 2500.00,
                'cost_guide' => 1500.00,
                'cost_activities' => 500.00,
                'group_size_min' => 2,
                'group_size_max' => 12,
                'start_latitude' => 34.1526,
                'start_longitude' => 77.5770,
                'altitude_min' => 3500,
                'altitude_max' => 5360,
                'best_seasons' => ['Summer'],
                'available_months' => [6, 7, 8, 9],
                'includes_accommodation' => false,
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => false,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Tirthan Valley Homestay',
                'slug' => 'tirthan-valley-homestay',
                'hlh_id' => $hlhDawaId,
                'region_id' => $regionIds['tirthan-valley'] ?? null,
                'type' => 'Nature',
                'short_description' => 'A relaxing 2-day homestay in Tirthan Valley with nature walks along the Tirthan river, trout fishing, and campfire evenings under starlit Himalayan skies.',
                'long_description' => 'Nestled in the buffer zone of the Great Himalayan National Park, this homestay experience offers a perfect blend of relaxation and mild adventure. Your host Dawa Dolma will welcome you with traditional Himachali cuisine. Days are spent walking forest trails, spotting Himalayan birds, and trying your hand at trout fishing in crystal-clear streams. Evenings bring campfire storytelling and a sky full of stars.',
                'duration_type' => 'multi_day',
                'duration_days' => 2,
                'duration_nights' => 1,
                'duration_hours' => null,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 5500.00,
                'cost_accommodation' => 2000.00,
                'cost_logistics' => 1000.00,
                'cost_guide' => 1000.00,
                'cost_activities' => 1000.00,
                'group_size_min' => 1,
                'group_size_max' => 6,
                'start_latitude' => 31.6380,
                'start_longitude' => 77.4480,
                'altitude_min' => 1500,
                'altitude_max' => 2000,
                'best_seasons' => ['Spring', 'Summer', 'Autumn'],
                'available_months' => [3, 4, 5, 6, 9, 10, 11],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => false,
                'includes_meals_dinner' => true,
                'includes_guide' => false,
                'includes_transport' => false,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Great Himalayan National Park Trek',
                'slug' => 'great-himalayan-national-park-trek',
                'hlh_id' => $hlhDawaId,
                'region_id' => $regionIds['tirthan-valley'] ?? null,
                'type' => 'Trek',
                'short_description' => 'A challenging 4-day trek into the heart of the Great Himalayan National Park, a UNESCO World Heritage Site. Traverse dense forests, alpine meadows, and glacial streams.',
                'long_description' => 'This demanding trek takes you deep into one of India\'s most pristine wilderness areas. From Gushaini, you ascend through temperate forests of oak and blue pine, passing waterfalls and river crossings. Higher up, the landscape opens into vast alpine meadows carpeted with wildflowers in season. The GHNP is home to the western tragopan, Himalayan brown bear, and snow leopard, though sightings require patience and luck.',
                'duration_type' => 'multi_day',
                'duration_days' => 4,
                'duration_nights' => 3,
                'duration_hours' => null,
                'difficulty_level' => 'challenging',
                'base_cost_per_person' => 15000.00,
                'cost_accommodation' => 3000.00,
                'cost_logistics' => 4000.00,
                'cost_guide' => 4000.00,
                'cost_activities' => 2000.00,
                'group_size_min' => 2,
                'group_size_max' => 10,
                'start_latitude' => 31.6350,
                'start_longitude' => 77.4520,
                'altitude_min' => 1500,
                'altitude_max' => 3700,
                'best_seasons' => ['Spring', 'Summer', 'Autumn'],
                'available_months' => [4, 5, 6, 9, 10],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Spiti Monastery Circuit',
                'slug' => 'spiti-monastery-circuit',
                'hlh_id' => $hlhDawaId, // Closest available HLH
                'region_id' => $regionIds['spiti-valley'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A 5-day journey through the ancient Buddhist monasteries of Spiti Valley, visiting Tabo, Dhankar, Key, and Kungri gompas while staying in traditional mud-brick villages.',
                'long_description' => 'This cultural circuit traverses the cold desert of Spiti, connecting monasteries that are among the oldest in the Tibetan Buddhist world. Tabo Gompa, founded in 996 AD, houses exquisite murals rivalling those of Ajanta. Dhankar sits perched on a cliff above the Spiti-Pin confluence. Key Monastery, the largest in Spiti, offers panoramic views of the valley. Between monasteries, you stay in village homestays, sharing meals and stories with families whose way of life has remained largely unchanged for centuries.',
                'duration_type' => 'multi_day',
                'duration_days' => 5,
                'duration_nights' => 4,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 18000.00,
                'cost_accommodation' => 5000.00,
                'cost_logistics' => 5000.00,
                'cost_guide' => 4000.00,
                'cost_activities' => 2000.00,
                'group_size_min' => 2,
                'group_size_max' => 8,
                'start_latitude' => 32.2460,
                'start_longitude' => 78.0350,
                'altitude_min' => 3600,
                'altitude_max' => 4200,
                'best_seasons' => ['Summer', 'Autumn'],
                'available_months' => [6, 7, 8, 9, 10],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => false,
                'road_seasonal_closure' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Pin Valley Snow Leopard Trail',
                'slug' => 'pin-valley-snow-leopard-trail',
                'hlh_id' => $hlhDawaId,
                'region_id' => $regionIds['spiti-valley'] ?? null,
                'type' => 'Wildlife',
                'short_description' => 'A winter wildlife expedition into Pin Valley National Park, tracking the elusive snow leopard with experienced local spotters. Includes ibex and Himalayan wildlife viewing.',
                'long_description' => 'Pin Valley is one of the best places in the world to spot the ghost cat of the Himalayas. In winter, snow leopards descend to lower elevations following their prey, the Himalayan ibex. Accompanied by expert local trackers from Kibber and Mud villages, you will scan the snow-dusted slopes for tracks and sightings. The experience includes stays in heated village homes, evening discussions on conservation, and the raw beauty of a Himalayan winter.',
                'duration_type' => 'multi_day',
                'duration_days' => 3,
                'duration_nights' => 2,
                'duration_hours' => null,
                'difficulty_level' => 'challenging',
                'base_cost_per_person' => 22000.00,
                'cost_accommodation' => 4000.00,
                'cost_logistics' => 6000.00,
                'cost_guide' => 6000.00,
                'cost_activities' => 4000.00,
                'group_size_min' => 2,
                'group_size_max' => 6,
                'start_latitude' => 32.3500,
                'start_longitude' => 78.0100,
                'altitude_min' => 3800,
                'altitude_max' => 4500,
                'best_seasons' => ['Winter'],
                'available_months' => [1, 2, 3],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Kinnaur Apple Orchard Stay',
                'slug' => 'kinnaur-apple-orchard-stay',
                'hlh_id' => $hlhDawaId,
                'region_id' => $regionIds['kinnaur'] ?? null,
                'type' => 'Culinary',
                'short_description' => 'Spend a day in the famous apple orchards of Kinnaur. Learn about organic apple farming, taste fresh cider, and enjoy a traditional Kinnauri lunch amid panoramic mountain views.',
                'long_description' => 'Kinnaur is renowned for its apple orchards that produce some of India\'s finest fruits. This single-day experience takes you into the orchards of a local farming family near Kalpa. You will learn about organic cultivation techniques, help with seasonal tasks (picking in autumn, pruning in spring), and taste varieties you have never encountered in city markets. A traditional Kinnauri thali lunch features local dishes like siddu, aktori, and patande, paired with freshly pressed apple cider.',
                'duration_type' => 'single_day',
                'duration_days' => 1,
                'duration_nights' => 0,
                'duration_hours' => null,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 3000.00,
                'cost_accommodation' => 0,
                'cost_logistics' => 1000.00,
                'cost_guide' => 500.00,
                'cost_activities' => 1000.00,
                'group_size_min' => 1,
                'group_size_max' => 10,
                'start_latitude' => 31.5400,
                'start_longitude' => 78.2600,
                'altitude_min' => 2700,
                'altitude_max' => 3000,
                'best_seasons' => ['Summer', 'Autumn'],
                'available_months' => [5, 6, 7, 8, 9, 10],
                'includes_accommodation' => false,
                'includes_meals_breakfast' => false,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => false,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Zanskar Frozen River Trek (Chadar)',
                'slug' => 'zanskar-frozen-river-trek-chadar',
                'hlh_id' => $hlhTseringId,
                'region_id' => $regionIds['zanskar'] ?? null,
                'type' => 'Adventure',
                'short_description' => 'The legendary Chadar Trek along the frozen Zanskar River. Walk on ice through a dramatic gorge in temperatures dropping to -30C over 7 days of extreme adventure.',
                'long_description' => 'The Chadar Trek is one of the most iconic and challenging treks in the world. When the Zanskar River freezes in deep winter, it becomes the only route connecting remote Zanskar villages to the outside world. You will walk on the frozen river surface through a narrow gorge with towering cliffs on either side, camp in caves used by Zanskari people for centuries, and experience the raw power of a Himalayan winter. This trek demands excellent fitness and mental resilience, but rewards with landscapes and experiences found nowhere else on Earth.',
                'duration_type' => 'multi_day',
                'duration_days' => 7,
                'duration_nights' => 6,
                'duration_hours' => null,
                'difficulty_level' => 'extreme',
                'base_cost_per_person' => 45000.00,
                'cost_accommodation' => 6000.00,
                'cost_logistics' => 15000.00,
                'cost_guide' => 12000.00,
                'cost_activities' => 8000.00,
                'group_size_min' => 4,
                'group_size_max' => 12,
                'start_latitude' => 34.1650,
                'start_longitude' => 77.5700,
                'end_latitude' => 33.5800,
                'end_longitude' => 76.8800,
                'altitude_min' => 3400,
                'altitude_max' => 3900,
                'best_seasons' => ['Winter'],
                'available_months' => [1, 2],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Sikkim Rhododendron Trail',
                'slug' => 'sikkim-rhododendron-trail',
                'hlh_id' => $hlhTseringId, // Nearest available HLH
                'region_id' => $regionIds['sikkim'] ?? null,
                'type' => 'Trek',
                'short_description' => 'A 3-day spring trek through Sikkim\'s famed rhododendron forests with views of Kanchenjunga. Bloom season transforms the trails into tunnels of crimson and pink.',
                'long_description' => 'Sikkim harbours over 600 species of rhododendron, and in spring the forests erupt in a riot of colour. This moderate trek follows trails through Barsey Rhododendron Sanctuary and the ridgeline paths above Pelling. The canopy shifts from red to pink to white as you gain altitude, with the majestic Kanchenjunga range providing a perpetual backdrop. Village stays introduce you to Sikkimese cuisine, including gundruk, kinema, and the ubiquitous tongba (fermented millet drink).',
                'duration_type' => 'multi_day',
                'duration_days' => 3,
                'duration_nights' => 2,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 12000.00,
                'cost_accommodation' => 3000.00,
                'cost_logistics' => 3000.00,
                'cost_guide' => 3000.00,
                'cost_activities' => 2000.00,
                'group_size_min' => 2,
                'group_size_max' => 10,
                'start_latitude' => 27.3200,
                'start_longitude' => 88.1500,
                'altitude_min' => 2000,
                'altitude_max' => 3600,
                'best_seasons' => ['Spring'],
                'available_months' => [3, 4, 5],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Kumaon Village Walk',
                'slug' => 'kumaon-village-walk',
                'hlh_id' => $hlhDawaId, // Nearest available HLH
                'region_id' => $regionIds['kumaon'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A gentle 4-hour walk through the charming villages of Kumaon, visiting ancient temples, interacting with local artisans, and enjoying panoramic views of the Nanda Devi range.',
                'long_description' => 'The Kumaon hills are dotted with villages that seem frozen in time. This half-day walk takes you through terraced farmlands, past stone-and-slate houses, and into the workshops of local weavers and metalworkers. A village elder shares stories of Kumaoni folklore, and you visit a centuries-old Shiva temple tucked into a deodar grove. The walk concludes with chai and local snacks at a family home overlooking the snow-capped peaks of Nanda Devi and Trishul.',
                'duration_type' => 'less_than_day',
                'duration_days' => null,
                'duration_nights' => null,
                'duration_hours' => 4.00,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 2000.00,
                'cost_accommodation' => 0,
                'cost_logistics' => 500.00,
                'cost_guide' => 1000.00,
                'cost_activities' => 500.00,
                'group_size_min' => 1,
                'group_size_max' => 15,
                'start_latitude' => 29.6300,
                'start_longitude' => 79.4300,
                'altitude_min' => 1800,
                'altitude_max' => 2100,
                'best_seasons' => ['Spring', 'Autumn', 'Winter'],
                'available_months' => [10, 11, 12, 1, 2, 3, 4],
                'includes_accommodation' => false,
                'includes_meals_breakfast' => false,
                'includes_meals_lunch' => false,
                'includes_meals_dinner' => false,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],

            // --- Nepal (5) ---
            [
                'name' => 'Everest Base Camp Trek',
                'slug' => 'everest-base-camp-trek',
                'hlh_id' => $hlhMayaId, // Nearest available HLH
                'region_id' => $regionIds['everest-region'] ?? null,
                'type' => 'Trek',
                'short_description' => 'The iconic 14-day trek to Everest Base Camp through Sherpa villages, ancient monasteries, and breathtaking high-altitude landscapes culminating at the foot of the world\'s highest peak.',
                'long_description' => 'Starting from Lukla with its famously short airstrip, this classic trek follows the Dudh Koshi valley through Namche Bazaar, Tengboche, and Dingboche before reaching Everest Base Camp at 5,364m. Along the way, you will experience the warmth of Sherpa hospitality, visit Tengboche Monastery with its stunning Everest backdrop, and acclimatize at Namche with its Saturday market. The final push to Base Camp and the viewpoint at Kala Patthar offers panoramic views of Everest, Lhotse, and Nuptse that will stay with you forever.',
                'duration_type' => 'multi_day',
                'duration_days' => 14,
                'duration_nights' => 13,
                'duration_hours' => null,
                'difficulty_level' => 'challenging',
                'base_cost_per_person' => 85000.00,
                'cost_accommodation' => 20000.00,
                'cost_logistics' => 25000.00,
                'cost_guide' => 20000.00,
                'cost_activities' => 10000.00,
                'group_size_min' => 2,
                'group_size_max' => 12,
                'start_latitude' => 27.6870,
                'start_longitude' => 86.7311,
                'end_latitude' => 28.0025,
                'end_longitude' => 86.8528,
                'altitude_min' => 2860,
                'altitude_max' => 5364,
                'best_seasons' => ['Spring', 'Autumn'],
                'available_months' => [3, 4, 5, 10, 11],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Annapurna Sunrise Trek',
                'slug' => 'annapurna-sunrise-trek',
                'hlh_id' => $hlhMayaId,
                'region_id' => $regionIds['annapurna'] ?? null,
                'type' => 'Trek',
                'short_description' => 'A 5-day trek through the Annapurna foothills to Poon Hill for the legendary sunrise view over Dhaulagiri and the Annapurna massif. Perfect for first-time trekkers in Nepal.',
                'long_description' => 'The Poon Hill trek is Nepal\'s most popular short trek for good reason. From Nayapul, you ascend through subtropical forests and terraced rice paddies to the Gurung villages of Ghorepani and Tadapani. The highlight is the pre-dawn climb to Poon Hill (3,210m) where you witness one of the most spectacular sunrises in the Himalayas, with over twenty peaks glowing gold and pink. The descent through mossy rhododendron forests to Ghandruk offers cultural encounters with the Gurung community.',
                'duration_type' => 'multi_day',
                'duration_days' => 5,
                'duration_nights' => 4,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 35000.00,
                'cost_accommodation' => 8000.00,
                'cost_logistics' => 10000.00,
                'cost_guide' => 8000.00,
                'cost_activities' => 5000.00,
                'group_size_min' => 2,
                'group_size_max' => 12,
                'start_latitude' => 28.3800,
                'start_longitude' => 83.6900,
                'end_latitude' => 28.3800,
                'end_longitude' => 83.8000,
                'altitude_min' => 1070,
                'altitude_max' => 3210,
                'best_seasons' => ['Spring', 'Autumn'],
                'available_months' => [3, 4, 5, 10, 11],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'Gurung Cultural Immersion',
                'slug' => 'gurung-cultural-immersion',
                'hlh_id' => $hlhMayaId,
                'region_id' => $regionIds['annapurna'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'Stay with a Gurung family in Ghandruk village, learn traditional dances, cook dal bhat from scratch, and hear stories of Gurkha heritage against the backdrop of Annapurna South.',
                'long_description' => 'The Gurung people of Ghandruk are renowned for their hospitality, martial traditions, and rich cultural heritage. This 2-day immersion places you in the home of Maya Tamang, where you will participate in daily life: grinding spices for dal bhat, learning the movements of traditional Gurung dances, and visiting the local museum documenting Gurkha military history. In the evening, gather around the fire for local raksi (millet spirits) and tales of yeti sightings in the nearby forests.',
                'duration_type' => 'multi_day',
                'duration_days' => 2,
                'duration_nights' => 1,
                'duration_hours' => null,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 6000.00,
                'cost_accommodation' => 2000.00,
                'cost_logistics' => 1000.00,
                'cost_guide' => 1500.00,
                'cost_activities' => 1000.00,
                'group_size_min' => 1,
                'group_size_max' => 6,
                'start_latitude' => 28.3800,
                'start_longitude' => 83.8100,
                'altitude_min' => 1940,
                'altitude_max' => 2100,
                'best_seasons' => ['Spring', 'Autumn', 'Winter'],
                'available_months' => [10, 11, 12, 1, 2, 3, 4],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => false,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Langtang Valley Trail',
                'slug' => 'langtang-valley-trail',
                'hlh_id' => $hlhMayaId, // Nearest available HLH
                'region_id' => $regionIds['langtang'] ?? null,
                'type' => 'Trek',
                'short_description' => 'A 7-day trek through the Langtang Valley, the valley of glaciers, with Tamang cultural encounters, yak pastures, and views of Langtang Lirung towering at 7,227m.',
                'long_description' => 'The Langtang Valley, closest to Kathmandu of Nepal\'s major trekking regions, offers an intimate mountain experience without the crowds. The trail follows the Langtang Khola through bamboo and rhododendron forests, past Tamang villages rebuilt after the 2015 earthquake, and into the high valley where yaks graze beneath glaciated peaks. Kyanjin Gompa at the valley head is a serene base for day hikes to Tserko Ri (4,984m) with its jaw-dropping panorama. The local cheese factory here makes some of Nepal\'s best yak cheese.',
                'duration_type' => 'multi_day',
                'duration_days' => 7,
                'duration_nights' => 6,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 42000.00,
                'cost_accommodation' => 10000.00,
                'cost_logistics' => 12000.00,
                'cost_guide' => 10000.00,
                'cost_activities' => 6000.00,
                'group_size_min' => 2,
                'group_size_max' => 10,
                'start_latitude' => 28.2137,
                'start_longitude' => 85.5150,
                'end_latitude' => 28.2100,
                'end_longitude' => 85.5700,
                'altitude_min' => 1500,
                'altitude_max' => 4984,
                'best_seasons' => ['Spring', 'Autumn'],
                'available_months' => [3, 4, 5, 10, 11],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'Kathmandu Spiritual Walk',
                'slug' => 'kathmandu-spiritual-walk',
                'hlh_id' => $hlhMayaId, // Nearest available HLH
                'region_id' => $regionIds['everest-region'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A 5-hour guided walk through Kathmandu\'s sacred sites: Boudhanath, Pashupatinath, and Swayambhunath stupas, exploring the intersection of Hindu and Buddhist traditions.',
                'long_description' => 'Kathmandu is one of the world\'s great spiritual crossroads, where Hinduism and Buddhism have coexisted and intermingled for over a millennium. This guided walk begins at Boudhanath, one of the largest Buddhist stupas in the world, where Tibetan refugees circumambulate with prayer wheels spinning. You proceed to Pashupatinath, the holiest Hindu temple in Nepal, where cremation ceremonies take place on the banks of the Bagmati River. The walk concludes at Swayambhunath (the Monkey Temple), perched on a hilltop with sweeping views of the Kathmandu Valley.',
                'duration_type' => 'less_than_day',
                'duration_days' => null,
                'duration_nights' => null,
                'duration_hours' => 5.00,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 3500.00,
                'cost_accommodation' => 0,
                'cost_logistics' => 1000.00,
                'cost_guide' => 1500.00,
                'cost_activities' => 500.00,
                'group_size_min' => 1,
                'group_size_max' => 15,
                'start_latitude' => 27.7215,
                'start_longitude' => 85.3620,
                'altitude_min' => 1300,
                'altitude_max' => 1400,
                'best_seasons' => ['Spring', 'Autumn', 'Winter'],
                'available_months' => [1, 2, 3, 4, 5, 10, 11, 12],
                'includes_accommodation' => false,
                'includes_meals_breakfast' => false,
                'includes_meals_lunch' => false,
                'includes_meals_dinner' => false,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 15,
            ],

            // --- Bhutan (3) ---
            [
                'name' => 'Tiger\'s Nest Pilgrimage',
                'slug' => 'tigers-nest-pilgrimage',
                'hlh_id' => $hlhKarmaId,
                'region_id' => $regionIds['paro-valley'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A single-day pilgrimage hike to the iconic Tiger\'s Nest (Paro Taktsang) monastery, clinging to a cliff face 900 metres above the valley floor, Bhutan\'s most sacred site.',
                'long_description' => 'Paro Taktsang is where Guru Rinpoche is said to have flown on the back of a tigress to meditate in a cave for three years, three months, three weeks, three days, and three hours. The hike from the valley floor takes roughly 2-3 hours through blue pine forest, with a tea stop at the cafeteria midway offering your first clear view of the monastery. The final approach crosses a waterfall-fed canyon via a bridge before ascending to the temple complex itself. Inside, the atmosphere is heavy with incense and devotion, and the views back down the valley are extraordinary.',
                'duration_type' => 'single_day',
                'duration_days' => 1,
                'duration_nights' => 0,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 5000.00,
                'cost_accommodation' => 0,
                'cost_logistics' => 1500.00,
                'cost_guide' => 2000.00,
                'cost_activities' => 1000.00,
                'group_size_min' => 1,
                'group_size_max' => 10,
                'start_latitude' => 27.4913,
                'start_longitude' => 89.3635,
                'altitude_min' => 2600,
                'altitude_max' => 3120,
                'best_seasons' => ['Spring', 'Autumn'],
                'available_months' => [3, 4, 5, 9, 10, 11],
                'includes_accommodation' => false,
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => false,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 16,
            ],
            [
                'name' => 'Bumthang Sacred Trail',
                'slug' => 'bumthang-sacred-trail',
                'hlh_id' => $hlhKarmaId,
                'region_id' => $regionIds['bumthang'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A 3-day walking trail through Bumthang\'s four sacred valleys, visiting the Jambay Lhakhang temple (7th century), Kurjey Lhakhang, and traditional Bhutanese farming communities.',
                'long_description' => 'Bumthang is considered the spiritual heartland of Bhutan, and this trail connects its most important sacred sites across four valleys: Chokhor, Tang, Ura, and Chumey. Jambay Lhakhang, one of 108 temples built by Tibetan King Songtsen Gampo in a single night to pin down a demoness, dates to 659 AD. Kurjey Lhakhang holds the body imprint of Guru Rinpoche. Between temples, you walk through pristine alpine valleys where farming communities maintain centuries-old traditions of buckwheat cultivation and yak herding.',
                'duration_type' => 'multi_day',
                'duration_days' => 3,
                'duration_nights' => 2,
                'duration_hours' => null,
                'difficulty_level' => 'moderate',
                'base_cost_per_person' => 15000.00,
                'cost_accommodation' => 5000.00,
                'cost_logistics' => 3000.00,
                'cost_guide' => 4000.00,
                'cost_activities' => 2000.00,
                'group_size_min' => 2,
                'group_size_max' => 8,
                'start_latitude' => 27.5500,
                'start_longitude' => 90.7300,
                'altitude_min' => 2600,
                'altitude_max' => 3400,
                'best_seasons' => ['Spring', 'Autumn'],
                'available_months' => [3, 4, 5, 9, 10, 11],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat C - Standard',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 17,
            ],
            [
                'name' => 'Bhutanese Farmhouse Stay',
                'slug' => 'bhutanese-farmhouse-stay',
                'hlh_id' => $hlhKarmaId,
                'region_id' => $regionIds['paro-valley'] ?? null,
                'type' => 'Nature',
                'short_description' => 'A 2-day stay in a traditional Bhutanese farmhouse in Paro Valley. Learn to cook ema datshi, try your hand at archery, and experience the deeply rooted happiness philosophy firsthand.',
                'long_description' => 'Bhutanese farmhouses are architectural marvels: rammed-earth structures painted white with intricately carved wooden windows. Your host family in Bondey village will guide you through daily routines that have changed little in centuries. You will learn to prepare ema datshi (chilli and cheese, the national dish), try your hand at archery (the national sport), and join in evening prayers at the family altar. The farmhouse sits amid rice paddies and apple orchards with views of the Paro Dzong and surrounding peaks.',
                'duration_type' => 'multi_day',
                'duration_days' => 2,
                'duration_nights' => 1,
                'duration_hours' => null,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 8000.00,
                'cost_accommodation' => 3000.00,
                'cost_logistics' => 1500.00,
                'cost_guide' => 2000.00,
                'cost_activities' => 1000.00,
                'group_size_min' => 1,
                'group_size_max' => 6,
                'start_latitude' => 27.3900,
                'start_longitude' => 89.3600,
                'altitude_min' => 2200,
                'altitude_max' => 2400,
                'best_seasons' => ['Spring', 'Autumn', 'Winter'],
                'available_months' => [2, 3, 4, 5, 9, 10, 11, 12],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 18,
            ],

            // --- Peru (2) ---
            [
                'name' => 'Inca Trail to Machu Picchu',
                'slug' => 'inca-trail-to-machu-picchu',
                'hlh_id' => $hlhKarmaId, // Nearest available HLH (no Peru HLH)
                'region_id' => $regionIds['sacred-valley'] ?? null,
                'type' => 'Trek',
                'short_description' => 'The classic 4-day trek along the ancient Inca Trail to Machu Picchu, passing through cloud forests, Inca ruins, and the Sun Gate for your first view of the lost citadel.',
                'long_description' => 'The Inca Trail is one of the world\'s great pilgrimages, following the original stone-paved road built by the Inca empire to connect their settlements to Machu Picchu. From Kilometre 82, you ascend through eucalyptus groves and past the ruins of Llactapata, cross the Dead Woman\'s Pass at 4,215m (the highest point), and descend through cloud forests thick with orchids and hummingbirds. On the final morning, you rise before dawn to reach Inti Punku (the Sun Gate) as the first light illuminates Machu Picchu below, one of the most iconic moments in travel.',
                'duration_type' => 'multi_day',
                'duration_days' => 4,
                'duration_nights' => 3,
                'duration_hours' => null,
                'difficulty_level' => 'challenging',
                'base_cost_per_person' => 75000.00,
                'cost_accommodation' => 15000.00,
                'cost_logistics' => 25000.00,
                'cost_guide' => 18000.00,
                'cost_activities' => 10000.00,
                'group_size_min' => 2,
                'group_size_max' => 12,
                'start_latitude' => -13.2500,
                'start_longitude' => -72.1900,
                'end_latitude' => -13.1631,
                'end_longitude' => -72.5450,
                'altitude_min' => 2400,
                'altitude_max' => 4215,
                'best_seasons' => ['Winter', 'Spring'],
                'available_months' => [5, 6, 7, 8, 9],
                'restricted_months' => [2],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => true,
                'is_active' => true,
                'sort_order' => 19,
            ],
            [
                'name' => 'Andean Community Immersion',
                'slug' => 'andean-community-immersion',
                'hlh_id' => $hlhKarmaId, // Nearest available HLH
                'region_id' => $regionIds['sacred-valley'] ?? null,
                'type' => 'Cultural Immersion',
                'short_description' => 'A 2-day stay with a Quechua community in the Sacred Valley, learning traditional weaving, potato cultivation, and Andean cosmovision while staying in a village homestay.',
                'long_description' => 'In the hills above Ollantaytambo, Quechua communities maintain traditions that predate the Inca Empire. This immersive experience places you in a village where Spanish is the second language and the rhythms of life follow the agricultural calendar. You will learn about the incredible diversity of native potatoes (Peru has over 3,000 varieties), try your hand at back-strap loom weaving using natural dyes, and participate in a despacho ceremony offering gratitude to Pachamama (Mother Earth). Evenings bring communal meals of pachamanca (earth oven) cooking under the Andean stars.',
                'duration_type' => 'multi_day',
                'duration_days' => 2,
                'duration_nights' => 1,
                'duration_hours' => null,
                'difficulty_level' => 'easy',
                'base_cost_per_person' => 12000.00,
                'cost_accommodation' => 3000.00,
                'cost_logistics' => 3000.00,
                'cost_guide' => 3000.00,
                'cost_activities' => 2000.00,
                'group_size_min' => 2,
                'group_size_max' => 8,
                'start_latitude' => -13.2580,
                'start_longitude' => -72.2640,
                'altitude_min' => 2800,
                'altitude_max' => 3500,
                'best_seasons' => ['Winter', 'Spring'],
                'available_months' => [4, 5, 6, 7, 8, 9, 10],
                'includes_accommodation' => true,
                'accommodation_category' => 'Cat D - Basic/Homestay',
                'includes_meals_breakfast' => true,
                'includes_meals_lunch' => true,
                'includes_meals_dinner' => true,
                'includes_guide' => true,
                'includes_transport' => true,
                'trekking_required' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];

        // Mark some as featured
        $featuredSlugs = [
            'village-experience-in-tar',
            'great-himalayan-national-park-trek',
            'zanskar-frozen-river-trek-chadar',
            'everest-base-camp-trek',
            'tigers-nest-pilgrimage',
            'inca-trail-to-machu-picchu',
        ];

        // Boilerplate defaults applied to every experience so no admin field
        // renders blank. Per-experience overrides win where the experience
        // array already specifies the field.
        $experience_defaults = [
            'unique_description'        => "What sets this experience apart is the genuine connection with local hosts and the chance to step into the daily rhythm of a Himalayan community. You travel light, leave a small footprint, and gain memories that last a lifetime.",
            'cultural_context'          => "The region's heritage is shaped by centuries of Buddhist tradition, mountain-pastoral life, and an unbroken connection to the surrounding landscape. Local communities maintain ancestral customs while adapting thoughtfully to modern realities — and travellers are invited to share in that quiet continuity.",
            'fitness_requirements'      => "Basic-to-moderate fitness sufficient. Be comfortable walking 4–6 hours on uneven mountain terrain. Prior trekking experience is helpful for moderate-to-challenging routes but not mandatory.",
            'age_min'                   => 12,
            'age_max'                   => 65,
            'weather_dependency'        => "Mountain weather can shift quickly. Snowfall, heavy rain, or landslides may lead to rerouting or rest days. Your trip designer builds in flexibility to keep the experience safe and enjoyable.",
            'cultural_sensitivities'    => "Dress modestly when visiting monasteries and homes. Walk clockwise around stupas and prayer wheels. Always ask before photographing people. Remove shoes inside dwellings and place religious objects on raised surfaces only.",
            'environmental_constraints' => "Carry out all non-biodegradable waste. Use a refillable water bottle — single-use plastics are discouraged across HECO trips. Stay on marked trails to protect fragile alpine vegetation, and respect wildlife by keeping distance.",
            'seasonality_notes'         => "The optimal window for this experience is governed by mountain weather and road accessibility. We recommend booking 30 days in advance during peak months to secure homestays and permits.",
            'traveller_bring_list'      => "Sturdy trekking shoes, layered clothing for cold mornings and warm afternoons, sunglasses, high-SPF sunscreen, lip balm, a refillable water bottle (1 L+), a personal first-aid kit with your regular medications, a headlamp with spare batteries, and a small daypack.",
            'clothing_recommendations'  => "Layering is essential. Pack thermal base layers, a fleece mid-layer, a windproof/waterproof outer shell, and a warm down jacket for evenings. Add a sun hat for daytime and a warm beanie plus gloves for early mornings.",
            'health_notes'              => "Consult your doctor before travelling at altitude. Carry Diamox if prescribed for AMS. Inform your trip designer of any pre-existing conditions, food allergies, or recent surgeries. Travel insurance covering high-altitude evacuation is strongly recommended.",
            'connectivity_notes'        => "Cellular signal is patchy or absent across remote sections. WiFi is generally only available in base towns. Inform family of your full itinerary before departure and carry an offline map.",
            'cultural_etiquette'        => "Greet locals with a smile and a slight bow. Accept tea or food offered by hosts — refusing is considered impolite. Don't point your feet at sacred images. Speak softly inside monasteries and prayer halls.",
            'operational_risks'         => "Altitude sickness, sudden weather changes, road blockages, and limited medical facilities are inherent risks in remote Himalayan travel. HECO carries emergency oxygen and maintains evacuation contacts; travellers should carry adequate travel insurance.",
            'past_issues'               => "No serious incidents reported on this route. Occasional minor delays due to seasonal road closures or short snowfall windows.",
            'backup_options'            => "If the primary route is blocked, the trip designer arranges alternative day-walks, cultural visits, and accommodation in nearby villages so the experience continues with minimal disruption.",
            'emergency_notes'           => "HECO 24×7 emergency line: +91 98765 43210. Nearest hospital, police station, and Indian embassy contacts (for international travellers) are shared with you on arrival.",
        ];

        foreach ($experiences as $expData) {
            // Skip if region not found
            if (empty($expData['region_id'])) {
                continue;
            }

            $isFeatured = in_array($expData['slug'], $featuredSlugs);

            // Build the create array, handling optional fields
            $createData = [
                'hlh_id' => $expData['hlh_id'],
                'region_id' => $expData['region_id'],
                'type' => $expData['type'],
                'short_description' => $expData['short_description'],
                'long_description' => $expData['long_description'] ?? null,
                'unique_description' => $expData['unique_description'] ?? $experience_defaults['unique_description'],
                'cultural_context' => $expData['cultural_context'] ?? $experience_defaults['cultural_context'],
                'duration_type' => $expData['duration_type'],
                'duration_hours' => $expData['duration_hours'] ?? null,
                'duration_days' => $expData['duration_days'] ?? null,
                'duration_nights' => $expData['duration_nights'] ?? null,
                'difficulty_level' => $expData['difficulty_level'],
                'fitness_requirements' => $expData['fitness_requirements'] ?? $experience_defaults['fitness_requirements'],
                'age_min' => $expData['age_min'] ?? $experience_defaults['age_min'],
                'age_max' => $expData['age_max'] ?? $experience_defaults['age_max'],
                'base_cost_per_person' => $expData['base_cost_per_person'],
                'cost_accommodation' => $expData['cost_accommodation'] ?? 0,
                'cost_logistics' => $expData['cost_logistics'] ?? 0,
                'cost_guide' => $expData['cost_guide'] ?? 0,
                'cost_activities' => $expData['cost_activities'] ?? 0,
                'group_size_min' => $expData['group_size_min'] ?? 1,
                'group_size_max' => $expData['group_size_max'] ?? null,
                'start_latitude' => $expData['start_latitude'] ?? null,
                'start_longitude' => $expData['start_longitude'] ?? null,
                'end_latitude' => $expData['end_latitude'] ?? null,
                'end_longitude' => $expData['end_longitude'] ?? null,
                'altitude_min' => $expData['altitude_min'] ?? null,
                'altitude_max' => $expData['altitude_max'] ?? null,
                'best_seasons' => $expData['best_seasons'] ?? null,
                'available_months' => $expData['available_months'] ?? null,
                'restricted_months' => $expData['restricted_months'] ?? null,
                'weather_dependency' => $expData['weather_dependency'] ?? $experience_defaults['weather_dependency'],
                'cultural_sensitivities' => $expData['cultural_sensitivities'] ?? $experience_defaults['cultural_sensitivities'],
                'environmental_constraints' => $expData['environmental_constraints'] ?? $experience_defaults['environmental_constraints'],
                'seasonality_notes' => $expData['seasonality_notes'] ?? $experience_defaults['seasonality_notes'],
                'traveller_bring_list' => $expData['traveller_bring_list'] ?? $experience_defaults['traveller_bring_list'],
                'clothing_recommendations' => $expData['clothing_recommendations'] ?? $experience_defaults['clothing_recommendations'],
                'health_notes' => $expData['health_notes'] ?? $experience_defaults['health_notes'],
                'connectivity_notes' => $expData['connectivity_notes'] ?? $experience_defaults['connectivity_notes'],
                'cultural_etiquette' => $expData['cultural_etiquette'] ?? $experience_defaults['cultural_etiquette'],
                'operational_risks' => $expData['operational_risks'] ?? $experience_defaults['operational_risks'],
                'past_issues' => $expData['past_issues'] ?? $experience_defaults['past_issues'],
                'backup_options' => $expData['backup_options'] ?? $experience_defaults['backup_options'],
                'emergency_notes' => $expData['emergency_notes'] ?? $experience_defaults['emergency_notes'],
                'includes_accommodation' => $expData['includes_accommodation'] ?? false,
                'accommodation_category' => $expData['accommodation_category'] ?? null,
                'includes_meals_breakfast' => $expData['includes_meals_breakfast'] ?? false,
                'includes_meals_lunch' => $expData['includes_meals_lunch'] ?? false,
                'includes_meals_dinner' => $expData['includes_meals_dinner'] ?? false,
                'includes_guide' => $expData['includes_guide'] ?? false,
                'includes_transport' => $expData['includes_transport'] ?? false,
                'trekking_required' => $expData['trekking_required'] ?? false,
                'road_seasonal_closure' => $expData['road_seasonal_closure'] ?? false,
                'is_active' => $expData['is_active'] ?? true,
                'sort_order' => $expData['sort_order'] ?? 0,
            ];

            Experience::updateOrCreate(
                ['slug' => $expData['slug']],
                array_merge(['name' => $expData['name']], $createData)
            );
        }

        // ─────────────────────────────────────────────────────────
        // 2b. EXPERIENCE DAYS — day-wise itinerary for each experience
        // ─────────────────────────────────────────────────────────

        // Standard inclusion combinations
        $incl_full       = ['Breakfast', 'Lunch', 'Dinner', 'Guide', 'Accommodation'];
        $incl_no_dinner  = ['Breakfast', 'Lunch', 'Guide', 'Accommodation'];
        $incl_bl_only    = ['Breakfast', 'Lunch', 'Guide'];
        $incl_single_day = ['Breakfast', 'Lunch', 'Guide', 'Transport'];

        $experience_days_data = [
            'village-experience-in-tar' => [
                [1, 'Arrival in Leh & Transfer to Tar', "Met at Leh airport and transferred to the village of Tar (45 min drive). Welcome tea with your host family, gentle walk through the village to acclimatise, and a traditional Ladakhi dinner.", '11:00', '20:00', $incl_no_dinner],
                [2, 'Gompa Mornings & Apricot Orchards', "Walk to the local gompa for morning prayers and butter-tea with the lama. Hands-on cooking class — make momos and thukpa with your host. Afternoon walk through apricot orchards toward sunset over the Stok range.", '06:30', '20:00', $incl_full],
                [3, 'Farewell Breakfast & Return', "Sunrise meditation on the rooftop. Hearty farewell breakfast and exchange of traditional gifts. Transfer back to Leh by mid-morning.", '06:00', '12:00', ['Breakfast', 'Guide']],
            ],
            'pangong-lake-expedition' => [
                [1, 'Pangong Lake Day Expedition', "Early start from Leh at 05:30. Drive over Chang La pass (5,360 m) with photo stops. Reach Pangong Lake by mid-morning. Packed lunch by the shoreline. Time to absorb the colour shifts before returning to Leh via Karu by evening.", '05:30', '20:00', $incl_single_day],
            ],
            'tirthan-valley-homestay' => [
                [1, 'Arrival & Tirthan River Walk', "Arrive at the homestay by lunchtime. Welcome meal of traditional Himachali cuisine. Afternoon walk along the Tirthan riverbank with a trout-fishing introduction. Campfire dinner and Himalayan stargazing.", '12:00', '22:00', $incl_no_dinner],
                [2, 'GHNP Buffer Zone & Farewell', "Early morning bird-watching walk in the buffer zone of Great Himalayan National Park. Hearty breakfast, then a short forest trek to a waterfall. Farewell lunch and return drive.", '06:00', '15:00', $incl_bl_only],
            ],
            'great-himalayan-national-park-trek' => [
                [1, 'Drive to Trailhead & Camp at Nada Thach', "Drive from Aut to the GHNP trailhead at Gushaini. Register at the park entry. Trek 3 hours through cedar forest to Nada Thach campsite (2,400 m).", '08:00', '18:00', $incl_full],
                [2, 'Ascend to Rolla Campsite', "Long but gentle trek through alpine meadows to Rolla (3,100 m). Spot Himalayan tahr and monal pheasant. Camp at Rolla.", '07:00', '17:00', $incl_full],
                [3, 'Upper Meadows Wildlife Day', "Day exploration around the upper meadows. Picnic lunch with sweeping views. Return to Rolla camp for one more night.", '07:30', '17:30', $incl_full],
                [4, 'Descent & Departure', "Pack camp and trek back down to Gushaini. Lunch at the park gate. Drive to your onward destination.", '07:00', '15:00', $incl_bl_only],
            ],
            'spiti-monastery-circuit' => [
                [1, 'Shimla → Reckong Peo', "Begin the high-altitude journey from Shimla. Long but scenic drive through Kinnaur. Overnight at Reckong Peo (2,290 m) for gradual acclimatisation.", '07:00', '19:00', $incl_full],
                [2, 'Reckong Peo → Tabo Monastery', "Drive into Spiti Valley along the Sutlej. Reach Tabo by afternoon. Visit the 996-year-old Tabo monastery — ancient murals and meditation caves.", '07:00', '19:00', $incl_full],
                [3, 'Dhankar & Pin Valley', "Morning visit to dramatically perched Dhankar monastery. Drive into Pin Valley National Park. Overnight at a Pin Valley homestay.", '07:30', '18:30', $incl_full],
                [4, 'Key Monastery & Kibber', "Visit iconic Key Gompa perched on a hilltop. Drive to Kibber (one of the world's highest villages with a road). Continue to Kaza.", '07:00', '18:00', $incl_full],
                [5, 'Komic, Hikkim & Return', "Morning visits to Komic and Hikkim (the highest post office in the world). Begin the descent back toward Shimla.", '07:00', '20:00', $incl_full],
            ],
            'pin-valley-snow-leopard-trail' => [
                [1, 'Drive to Mud Village', "Drive from Kaza into Pin Valley National Park. Settle into a homestay in Mud village (3,810 m). Evening briefing by your local naturalist on tracking ethics.", '08:00', '19:00', $incl_full],
                [2, 'Wildlife Spotting Day', "Full day with the naturalist at known snow-leopard sighting ridges. Patient observation from prayer-flag viewpoints. Hot lunch carried up to the vantage spot.", '06:30', '17:30', $incl_full],
                [3, 'Final Morning & Return', "Last attempt at a morning sighting before breakfast. Pack out and drive back to Kaza by afternoon.", '06:00', '15:00', $incl_bl_only],
            ],
            'kinnaur-apple-orchard-stay' => [
                [1, 'Sangla Apple Orchard Day', "Drive to Sangla in Baspa Valley. Guided orchard tour with the host family explaining grafting and harvest practices. Traditional Kinnauri lunch served outdoors. Cider tasting in the evening. Return drive.", '08:00', '20:00', $incl_single_day],
            ],
            'zanskar-frozen-river-trek-chadar' => [
                [1, 'Arrive in Leh', "Arrive at Leh airport. Full day of rest to begin acclimatising to 3,500 m. Light walk in Old Leh in the afternoon.", '10:00', '20:00', $incl_no_dinner],
                [2, 'Acclimatisation Day', "Mandatory rest day. Slow walk to Shanti Stupa for views. Pre-trek medical check-up and gear briefing.", '08:00', '19:00', $incl_full],
                [3, 'Drive to Chilling, Trek to Tilad Do', "Drive from Leh to Chilling (3,200 m) where the road ends. Step onto the frozen river. Short trek to Tilad Do camp.", '07:00', '18:00', $incl_full],
                [4, 'Chadar Trek to Shingra Koma', "Six hours of careful walking on the ice. Cross frozen waterfalls and narrow gorge sections. Camp on the river bank.", '08:00', '17:30', $incl_full],
                [5, 'Trek to Tibb Cave', "Continue along the Chadar. Tibb cave shelters the team for the night — the cave has hosted Zanskari traders for centuries.", '08:00', '17:00', $incl_full],
                [6, 'Reach Nyerak Village', "Final push to Nyerak village. Visit the frozen waterfall above the village — a Chadar trek highlight.", '08:00', '17:30', $incl_full],
                [7, 'Return Trek & Drive Back', "Retrace the route in stages. The final day reaches Chilling and drives back to Leh by evening.", '07:00', '20:00', $incl_full],
            ],
            'sikkim-rhododendron-trail' => [
                [1, 'Pelling to Yuksom', "Drive from Pelling to Yuksom (1,780 m), the trailhead. Visit Dubdi monastery in the afternoon. Settle into a Yuksom homestay.", '09:00', '18:00', $incl_full],
                [2, 'Rhododendron Forest Trek to Bakhim', "Trek through dense rhododendron forest in full bloom (April–May). Lunch by the Prek river. Reach Bakhim camp (2,740 m) by evening.", '07:00', '17:30', $incl_full],
                [3, 'Descent & Return', "Morning views of Kanchenjunga from a nearby viewpoint. Trek down to Yuksom. Drive back to Pelling.", '06:30', '17:00', $incl_full],
            ],
            'kumaon-village-walk' => [
                [1, 'Village Walk near Almora', "A guided 4-hour walk through traditional Kumaoni villages around Almora. Visit a working pahari kitchen, meet local weavers, and end with a hot chai at a viewpoint overlooking the Trishul peaks.", '09:00', '13:30', ['Lunch', 'Guide']],
            ],
            'everest-base-camp-trek' => [
                [1, 'Arrive Kathmandu', "Arrive in Kathmandu. Trip briefing and gear check at the hotel. Welcome dinner with the trek leader.", '14:00', '21:00', $incl_no_dinner],
                [2, 'Fly to Lukla & Trek to Phakding', "Early morning flight to Lukla (2,840 m). Begin the trek with an easy 3-hour walk to Phakding (2,610 m).", '05:30', '15:00', $incl_full],
                [3, 'Phakding to Namche Bazaar', "Trek alongside the Dudh Koshi river crossing several suspension bridges. Steep climb to Namche Bazaar (3,440 m).", '07:30', '17:00', $incl_full],
                [4, 'Acclimatisation in Namche', "Rest and acclimatise. Optional hike up to Everest View Hotel for the first glimpse of Everest. Visit the Sherpa museum.", '08:00', '17:00', $incl_full],
                [5, 'Namche to Tengboche', "Trek to Tengboche (3,860 m) home to the largest monastery in the Khumbu region. Attend evening prayers.", '07:30', '17:00', $incl_full],
                [6, 'Tengboche to Dingboche', "Climb past Pangboche to Dingboche (4,410 m). Expansive views of Ama Dablam and Lhotse.", '07:30', '16:30', $incl_full],
                [7, 'Acclimatisation in Dingboche', "Second mandatory rest day. Optional walk to Nangkartshang viewpoint (5,083 m) for acclimatisation.", '08:00', '16:00', $incl_full],
                [8, 'Dingboche to Lobuche', "Cross the Khumbu glacier moraine to Lobuche (4,940 m). Pass memorial stones for Everest climbers along the Thukla pass.", '07:00', '16:30', $incl_full],
                [9, 'Lobuche to EBC via Gorak Shep', "Trek to Gorak Shep (5,164 m) then continue to Everest Base Camp (5,364 m). Return to Gorak Shep for the night.", '06:30', '18:00', $incl_full],
                [10, 'Kala Patthar Sunrise & Descent', "Pre-dawn climb to Kala Patthar (5,545 m) for sunrise over Everest. Descend all the way to Pheriche.", '04:00', '17:00', $incl_full],
                [11, 'Pheriche to Namche', "Long descent back to Namche Bazaar. Celebrate at one of Namche's bakeries.", '07:30', '17:30', $incl_full],
                [12, 'Namche to Lukla', "Final trek day back to Lukla. Farewell dinner with the trekking crew.", '07:30', '17:00', $incl_full],
                [13, 'Fly Lukla → Kathmandu', "Early flight back to Kathmandu. Rest of the day at leisure.", '06:00', '12:00', $incl_no_dinner],
                [14, 'Departure', "Transfer to Kathmandu airport for your onward flight.", '06:00', '12:00', ['Breakfast']],
            ],
            'annapurna-sunrise-trek' => [
                [1, 'Kathmandu → Pokhara', "Drive or fly to Pokhara (820 m). Lakeside evening at Phewa Lake.", '08:00', '18:00', $incl_no_dinner],
                [2, 'Trek to Tikhedhunga', "Drive to Nayapul and start trekking. 4-hour trek to Tikhedhunga (1,540 m).", '08:00', '16:30', $incl_full],
                [3, 'Tikhedhunga to Ghorepani', "Long climb up the famous 3,200 stone steps to Ulleri, then through rhododendron forest to Ghorepani (2,860 m).", '07:00', '17:30', $incl_full],
                [4, 'Poon Hill Sunrise & Tadapani', "Pre-dawn ascent of Poon Hill (3,210 m) for sunrise over Dhaulagiri and Annapurna. Descend, breakfast, then trek to Tadapani.", '04:30', '17:00', $incl_full],
                [5, 'Ghandruk → Pokhara → Return', "Descend through Ghandruk village. Drive back to Pokhara.", '07:00', '18:00', $incl_bl_only],
            ],
            'gurung-cultural-immersion' => [
                [1, 'Pokhara to Gurung Village', "Drive from Pokhara to a traditional Gurung village. Welcome with marigold garlands. Evening cultural performance — folk dance and instruments. Traditional dhindo dinner with the family.", '09:00', '21:30', $incl_no_dinner],
                [2, 'Village Walk & Craft Workshop', "Morning walk to the village viewpoint for Annapurna views. Bamboo basket weaving workshop with a local artisan. Farewell lunch, return to Pokhara.", '06:30', '15:00', $incl_bl_only],
            ],
            'langtang-valley-trail' => [
                [1, 'Kathmandu → Syabrubesi', "Drive to Syabrubesi (1,550 m), the trailhead. Long but scenic journey along the Trishuli river.", '07:00', '17:00', $incl_full],
                [2, 'Trek to Lama Hotel', "Begin the trek through bamboo and rhododendron forest. Cross the Langtang Khola. Overnight at Lama Hotel (2,470 m).", '08:00', '16:30', $incl_full],
                [3, 'Trek to Langtang Village', "Steady climb to Langtang village (3,430 m). Walk through the village which was rebuilt after the 2015 earthquake.", '08:00', '16:30', $incl_full],
                [4, 'Langtang to Kyanjin Gompa', "Short half-day trek to Kyanjin Gompa (3,870 m). Visit the cheese factory and the ancient gompa.", '08:30', '14:00', $incl_full],
                [5, 'Acclimatisation & Kyanjin Ri', "Day hike up to Kyanjin Ri (4,773 m) for panoramic views of the Langtang range. Return for lunch.", '06:30', '15:30', $incl_full],
                [6, 'Descend to Lama Hotel', "Retrace the trail back down to Lama Hotel.", '07:30', '17:00', $incl_full],
                [7, 'Lama Hotel to Syabrubesi & Return', "Final descent to Syabrubesi. Drive back to Kathmandu.", '07:00', '20:00', $incl_full],
            ],
            'kathmandu-spiritual-walk' => [
                [1, 'Three-Stupa Spiritual Walk', "A guided half-day walk linking the three great pilgrimage sites of Kathmandu Valley — Boudhanath (Buddhist stupa), Pashupatinath (Hindu shrine on the Bagmati), and Swayambhunath (the Monkey Temple). Lunch at a local Newari restaurant.", '08:00', '14:00', ['Lunch', 'Guide', 'Transport']],
            ],
            'tigers-nest-pilgrimage' => [
                [1, "Tiger's Nest Day Pilgrimage", "Drive from Paro to the Taktsang trailhead. Steady 3-hour ascent to the Tiger's Nest monastery (3,120 m) clinging to a cliff face. Tour the inner shrines with a local guide. Picnic lunch at the cafeteria viewpoint. Descend back to Paro.", '07:30', '18:00', $incl_single_day],
            ],
            'bumthang-sacred-trail' => [
                [1, 'Drive to Bumthang', "Drive from Paro to Bumthang valley (2,580 m) — one of the longest drives in Bhutan but past stunning passes. Settle into a heritage farmhouse.", '08:00', '19:00', $incl_full],
                [2, 'Sacred Temples Tour', "Visit Jakar Dzong, Kurjey Lhakhang (with Guru Rinpoche's body imprint), and Jambay Lhakhang (one of Bhutan's oldest temples). Picnic lunch in the meadows.", '08:30', '18:00', $incl_full],
                [3, 'Tang Valley & Departure', "Drive to Tang valley to visit Ogyen Choling — a 100-year-old noble household turned museum. Return to Paro late afternoon.", '08:00', '19:30', $incl_full],
            ],
            'bhutanese-farmhouse-stay' => [
                [1, 'Arrive at the Farmhouse', "Drive from Paro to a traditional Paro-valley farmhouse. Tour the working farm — buckwheat fields, livestock, and chilli drying racks. Traditional ema-datshi dinner. Hot-stone bath under the stars.", '11:00', '21:30', $incl_no_dinner],
                [2, 'Farm Activities & Farewell', "Help with morning chores — milking, grain milling, or vegetable harvesting. Bhutanese breakfast of suja and zow. Farewell ceremony with the family.", '06:30', '13:00', ['Breakfast', 'Guide']],
            ],
            'inca-trail-to-machu-picchu' => [
                [1, 'KM 82 to Wayllabamba', "Briefing in Cusco at dawn. Drive to KM 82, the trailhead. Walk along the Urubamba river. First camp at Wayllabamba (3,000 m).", '05:00', '17:00', $incl_full],
                [2, "Dead Woman's Pass to Pacaymayo", "The hardest day. Steady climb to Warmiwañusca (Dead Woman's Pass, 4,215 m). Descend to Pacaymayo camp.", '06:30', '17:30', $incl_full],
                [3, 'Phuyupatamarca to Wiñay Wayna', "Pass three Inca ruins along the way — Runkurakay, Sayacmarca, and Phuyupatamarca. Final descent through cloud forest to Wiñay Wayna camp.", '06:30', '17:30', $incl_full],
                [4, 'Sun Gate & Machu Picchu', "Pre-dawn start to reach the Sun Gate (Inti Punku) for sunrise over Machu Picchu. Guided tour of the citadel. Train and bus back to Cusco.", '03:30', '20:00', $incl_full],
            ],
            'andean-community-immersion' => [
                [1, 'Cusco to Community & Weaving Workshop', "Drive from Cusco to a high-altitude Quechua community. Welcome with coca-leaf tea. Hands-on weaving workshop with the women's collective. Traditional pachamanca lunch cooked underground.", '08:30', '20:00', $incl_no_dinner],
                [2, 'Farm Walk & Textile Market', "Morning farm walk through potato terraces and llama pastures. Visit the textile cooperative market. Farewell lunch and return drive to Cusco.", '06:30', '17:00', $incl_bl_only],
            ],
        ];

        // Wipe any leftover empty ExperienceDay rows from prior test runs, then
        // upsert a clean day-wise itinerary for each experience by slug.
        \App\Models\ExperienceDay::whereNull('title')->orWhere('title', '')->delete();

        foreach ($experience_days_data as $slug => $days) {
            $exp = Experience::where('slug', $slug)->first();
            if (!$exp) continue;
            foreach ($days as $idx => $d) {
                [$day_number, $title, $short_desc, $start, $end, $inclusions] = $d;
                \App\Models\ExperienceDay::updateOrCreate(
                    ['experience_id' => $exp->id, 'day_number' => $day_number],
                    [
                        'title' => $title,
                        'short_description' => $short_desc,
                        'start_time' => $start,
                        'end_time' => $end,
                        'inclusions' => $inclusions,
                        'sort_order' => $idx,
                    ]
                );
            }
        }

        // ─────────────────────────────────────────────────────────
        // 3. TRAVELLER USERS (4 additional records)
        // ─────────────────────────────────────────────────────────

        $travellerJohn = User::firstOrCreate(
            ['email' => 'john.doe@gmail.com'],
            [
                'full_name' => 'John Doe',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'traveller',
                'mobile' => '+1-415-555-0123',
                'status' => 'active',
            ]
        );

        $travellerSarah = User::firstOrCreate(
            ['email' => 'sarah.chen@yahoo.com'],
            [
                'full_name' => 'Sarah Chen',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'traveller',
                'mobile' => '+65-9123-4567',
                'status' => 'active',
            ]
        );

        $travellerMarc = User::firstOrCreate(
            ['email' => 'marc.dubois@gmail.com'],
            [
                'full_name' => 'Marc Dubois',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'traveller',
                'mobile' => '+33-6-1234-5678',
                'status' => 'active',
            ]
        );

        $travellerPriya = User::firstOrCreate(
            ['email' => 'priya.sharma@outlook.com'],
            [
                'full_name' => 'Priya Sharma',
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => 'traveller',
                'mobile' => '+91-9876543210',
                'status' => 'active',
            ]
        );

        // ─────────────────────────────────────────────────────────
        // 4. TRIPS (8 records — needed for leads)
        // ─────────────────────────────────────────────────────────

        $trips = [];

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0101'],
            [
                'user_id' => $travellerJohn->id,
                'trip_name' => 'Ladakh Cultural Discovery',
                'status' => 'confirmed',
                'stage' => 'open',
                'traveller_origin' => 'foreigner',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-06-15'),
                'end_date' => Carbon::parse('2026-06-22'),
                'start_location' => 'Leh Airport',
                'end_location' => 'Leh Airport',
                'pickup_location' => 'Leh Airport',
                'pickup_time' => '10:00',
                'drop_location' => 'Leh Airport',
                'drop_time' => '14:00',
                'accommodation_comfort' => 'Cat C - Standard',
                'vehicle_comfort' => 'SUV (Innova/Crysta)',
                'guide_preference' => 'English-speaking',
                'travel_pace' => 'Moderate',
                'budget_sensitivity' => 'Mid-range',
                'transport_cost' => 18000.00,
                'accommodation_cost' => 14000.00,
                'guide_cost' => 8000.00,
                'activity_cost' => 6000.00,
                'other_cost' => 2000.00,
                'total_cost' => 48000.00,
                'commission_hct_percent' => 15.00,
                'commission_hct_amount' => 7200.00,
                'subtotal' => 55200.00,
                'gst_amount' => 2760.00,
                'final_price' => 57960.00,
                'general_notes' => 'First-time visitors to India. Interested in Buddhist culture and photography.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0102'],
            [
                'user_id' => $travellerSarah->id,
                'trip_name' => 'Annapurna & Everest Adventure',
                'status' => 'not_confirmed',
                'stage' => 'open',
                'traveller_origin' => 'foreigner',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-10-05'),
                'end_date' => Carbon::parse('2026-10-20'),
                'start_location' => 'Kathmandu Airport',
                'end_location' => 'Kathmandu Airport',
                'pickup_location' => 'Kathmandu Airport',
                'pickup_time' => '12:00',
                'accommodation_comfort' => 'Cat D - Basic/Homestay',
                'vehicle_comfort' => 'SUV (Bolero/Scorpio)',
                'guide_preference' => 'English-speaking',
                'travel_pace' => 'Active',
                'budget_sensitivity' => 'Budget-friendly',
                'transport_cost' => 15000.00,
                'accommodation_cost' => 20000.00,
                'guide_cost' => 18000.00,
                'activity_cost' => 12000.00,
                'other_cost' => 5000.00,
                'total_cost' => 70000.00,
                'commission_hct_percent' => 12.00,
                'commission_hct_amount' => 8400.00,
                'subtotal' => 78400.00,
                'gst_amount' => 3920.00,
                'final_price' => 82320.00,
                'general_notes' => 'Solo female traveller. Experienced trekker. Wants to combine Poon Hill with EBC.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0103'],
            [
                'user_id' => $travellerMarc->id,
                'trip_name' => 'Spiti Valley Expedition',
                'status' => 'confirmed',
                'stage' => 'open',
                'traveller_origin' => 'foreigner',
                'adults' => 2,
                'children' => 1,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-07-10'),
                'end_date' => Carbon::parse('2026-07-20'),
                'start_location' => 'Manali',
                'end_location' => 'Shimla',
                'pickup_location' => 'Manali Bus Stand',
                'pickup_time' => '08:00',
                'drop_location' => 'Shimla Railway Station',
                'drop_time' => '18:00',
                'accommodation_comfort' => 'Cat C - Standard',
                'vehicle_comfort' => 'SUV (Innova/Crysta)',
                'guide_preference' => 'French or English speaking',
                'travel_pace' => 'Relaxed',
                'budget_sensitivity' => 'Mid-range',
                'transport_cost' => 25000.00,
                'accommodation_cost' => 18000.00,
                'guide_cost' => 12000.00,
                'activity_cost' => 8000.00,
                'other_cost' => 3000.00,
                'total_cost' => 66000.00,
                'commission_hct_percent' => 15.00,
                'commission_hct_amount' => 9900.00,
                'subtotal' => 75900.00,
                'gst_amount' => 3795.00,
                'final_price' => 79695.00,
                'general_notes' => 'Family trip with 8-year-old child. Interested in monasteries and landscape photography. Prefers not too strenuous.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0104'],
            [
                'user_id' => $travellerPriya->id,
                'trip_name' => 'Tirthan Valley Weekend',
                'status' => 'completed',
                'stage' => 'closed',
                'traveller_origin' => 'indian',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2025-10-18'),
                'end_date' => Carbon::parse('2025-10-20'),
                'start_location' => 'Aut',
                'end_location' => 'Aut',
                'pickup_location' => 'Aut Bus Stand',
                'pickup_time' => '11:00',
                'drop_location' => 'Aut Bus Stand',
                'drop_time' => '16:00',
                'accommodation_comfort' => 'Cat D - Basic/Homestay',
                'travel_pace' => 'Relaxed',
                'budget_sensitivity' => 'Budget-friendly',
                'transport_cost' => 3000.00,
                'accommodation_cost' => 4000.00,
                'guide_cost' => 2000.00,
                'activity_cost' => 2000.00,
                'other_cost' => 1000.00,
                'total_cost' => 12000.00,
                'commission_hct_percent' => 10.00,
                'commission_hct_amount' => 1200.00,
                'subtotal' => 13200.00,
                'gst_amount' => 660.00,
                'final_price' => 13860.00,
                'general_notes' => 'Repeat traveller. Loved the homestay experience. Wants to return for GHNP trek.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0105'],
            [
                'user_id' => $travellerJohn->id,
                'trip_name' => 'Bhutan Tiger\'s Nest',
                'status' => 'not_confirmed',
                'stage' => 'open',
                'traveller_origin' => 'foreigner',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-11-01'),
                'end_date' => Carbon::parse('2026-11-05'),
                'start_location' => 'Paro Airport',
                'end_location' => 'Paro Airport',
                'accommodation_comfort' => 'Cat C - Standard',
                'travel_pace' => 'Moderate',
                'budget_sensitivity' => 'Premium',
                'transport_cost' => 8000.00,
                'accommodation_cost' => 15000.00,
                'guide_cost' => 10000.00,
                'activity_cost' => 5000.00,
                'other_cost' => 2000.00,
                'total_cost' => 40000.00,
                'commission_hct_percent' => 15.00,
                'commission_hct_amount' => 6000.00,
                'subtotal' => 46000.00,
                'gst_amount' => 2300.00,
                'final_price' => 48300.00,
                'general_notes' => 'Extension after Ladakh trip. Wants the full Bhutan cultural experience.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0106'],
            [
                'user_id' => $travellerSarah->id,
                'trip_name' => 'Sacred Valley Peru',
                'status' => 'not_confirmed',
                'stage' => 'open',
                'traveller_origin' => 'foreigner',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-08-15'),
                'end_date' => Carbon::parse('2026-08-22'),
                'start_location' => 'Cusco Airport',
                'end_location' => 'Cusco Airport',
                'accommodation_comfort' => 'Cat C - Standard',
                'travel_pace' => 'Active',
                'budget_sensitivity' => 'Mid-range',
                'transport_cost' => 20000.00,
                'accommodation_cost' => 18000.00,
                'guide_cost' => 15000.00,
                'activity_cost' => 10000.00,
                'other_cost' => 5000.00,
                'total_cost' => 68000.00,
                'commission_hct_percent' => 12.00,
                'commission_hct_amount' => 8160.00,
                'subtotal' => 76160.00,
                'gst_amount' => 3808.00,
                'final_price' => 79968.00,
                'general_notes' => 'Interested in Inca Trail + community immersion. Has altitude experience from Nepal treks.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0107'],
            [
                'user_id' => $travellerMarc->id,
                'trip_name' => 'Chadar Trek Winter',
                'status' => 'cancelled',
                'stage' => 'closed',
                'traveller_origin' => 'foreigner',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-01-20'),
                'end_date' => Carbon::parse('2026-01-28'),
                'start_location' => 'Leh Airport',
                'end_location' => 'Leh Airport',
                'accommodation_comfort' => 'Cat D - Basic/Homestay',
                'travel_pace' => 'Active',
                'budget_sensitivity' => 'Premium',
                'transport_cost' => 12000.00,
                'accommodation_cost' => 10000.00,
                'guide_cost' => 15000.00,
                'activity_cost' => 20000.00,
                'other_cost' => 5000.00,
                'total_cost' => 62000.00,
                'commission_hct_percent' => 15.00,
                'commission_hct_amount' => 9300.00,
                'subtotal' => 71300.00,
                'gst_amount' => 3565.00,
                'final_price' => 74865.00,
                'general_notes' => 'Cancelled due to insufficient river freezing conditions. Rescheduled to next year.',
            ]
        );

        $trips[] = Trip::updateOrCreate(
            ['trip_id' => 'HECO-T-0108'],
            [
                'user_id' => $travellerPriya->id,
                'trip_name' => 'Sikkim Spring Trek',
                'status' => 'not_confirmed',
                'stage' => 'open',
                'traveller_origin' => 'indian',
                'adults' => 3,
                'children' => 0,
                'infants' => 0,
                'start_date' => Carbon::parse('2026-04-01'),
                'end_date' => Carbon::parse('2026-04-05'),
                'start_location' => 'Bagdogra Airport',
                'end_location' => 'Bagdogra Airport',
                'accommodation_comfort' => 'Cat C - Standard',
                'travel_pace' => 'Moderate',
                'budget_sensitivity' => 'Mid-range',
                'transport_cost' => 10000.00,
                'accommodation_cost' => 9000.00,
                'guide_cost' => 6000.00,
                'activity_cost' => 4000.00,
                'other_cost' => 2000.00,
                'total_cost' => 31000.00,
                'commission_hct_percent' => 12.00,
                'commission_hct_amount' => 3720.00,
                'subtotal' => 34720.00,
                'gst_amount' => 1736.00,
                'final_price' => 36456.00,
                'general_notes' => 'Group of 3 friends. Want to see rhododendron blooms and Kanchenjunga views.',
            ]
        );

        // ─────────────────────────────────────────────────────────
        // 5. LEADS (8 records)
        // ─────────────────────────────────────────────────────────

        $hctAdmin = User::where('email', 'admin@hecoapp.com')->first();
        $hctCollab = User::where('email', 'collaborator@hecoapp.com')->first();

        // Lead 1 - follow_up (John's Ladakh trip)
        Lead::updateOrCreate(
            ['trip_id' => $trips[0]->id],
            [
                'user_id' => $travellerJohn->id,
                'assigned_hct_id' => $hctAdmin?->id,
                'stage' => 'follow_up',
                'enquiry_date' => Carbon::parse('2026-01-10'),
                'last_interaction_date' => Carbon::parse('2026-02-05'),
                'interaction_mode' => 'whatsapp',
                'reminder_delay_days' => 3,
                'notes' => 'Traveller confirmed dates. Waiting on hotel availability in Leh for June. Follow up on accommodation options by Feb 10.',
            ]
        );

        // Lead 2 - follow_up (Sarah's Nepal trip)
        Lead::updateOrCreate(
            ['trip_id' => $trips[1]->id],
            [
                'user_id' => $travellerSarah->id,
                'assigned_hct_id' => $hctCollab?->id,
                'stage' => 'follow_up',
                'enquiry_date' => Carbon::parse('2026-01-15'),
                'last_interaction_date' => Carbon::parse('2026-02-01'),
                'interaction_mode' => 'email',
                'reminder_delay_days' => 5,
                'notes' => 'Sarah interested in combining Poon Hill + EBC. Concerned about fitness level for EBC. Sent her a training guide. Follow up next week.',
            ]
        );

        // Lead 3 - follow_up (Marc's Spiti trip)
        Lead::updateOrCreate(
            ['trip_id' => $trips[2]->id],
            [
                'user_id' => $travellerMarc->id,
                'assigned_hct_id' => $hctAdmin?->id,
                'stage' => 'follow_up',
                'enquiry_date' => Carbon::parse('2026-02-01'),
                'last_interaction_date' => Carbon::parse('2026-02-10'),
                'interaction_mode' => 'call',
                'reminder_delay_days' => 3,
                'notes' => 'Family trip confirmed but still deciding on child-friendly activities. Suggested Spiti Monastery Circuit with shorter daily walks. Awaiting final itinerary approval.',
            ]
        );

        // Lead 4 - follow_up (John's Bhutan trip)
        Lead::updateOrCreate(
            ['trip_id' => $trips[4]->id],
            [
                'user_id' => $travellerJohn->id,
                'assigned_hct_id' => $hctCollab?->id,
                'stage' => 'follow_up',
                'enquiry_date' => Carbon::parse('2026-02-08'),
                'last_interaction_date' => Carbon::parse('2026-02-12'),
                'interaction_mode' => 'whatsapp',
                'reminder_delay_days' => 7,
                'notes' => 'John wants to extend his Ladakh trip with a Bhutan add-on. Checking visa processing times and Druk Air availability for November.',
            ]
        );

        // Lead 5 - won (Priya's Tirthan trip - completed)
        Lead::updateOrCreate(
            ['trip_id' => $trips[3]->id],
            [
                'user_id' => $travellerPriya->id,
                'assigned_hct_id' => $hctAdmin?->id,
                'stage' => 'won',
                'enquiry_date' => Carbon::parse('2025-09-20'),
                'last_interaction_date' => Carbon::parse('2025-10-20'),
                'interaction_mode' => 'whatsapp',
                'reminder_delay_days' => 3,
                'notes' => 'Trip completed successfully. Priya gave excellent feedback on homestay. Wants to return for GHNP trek in spring 2026. Potential repeat customer.',
            ]
        );

        // Lead 6 - won (Marc's confirmed Spiti — treating as won since confirmed + payment done)
        Lead::updateOrCreate(
            ['trip_id' => $trips[5]->id],
            [
                'user_id' => $travellerSarah->id,
                'assigned_hct_id' => $hctAdmin?->id,
                'stage' => 'won',
                'enquiry_date' => Carbon::parse('2025-12-01'),
                'last_interaction_date' => Carbon::parse('2026-01-15'),
                'interaction_mode' => 'email',
                'reminder_delay_days' => 5,
                'notes' => 'Sarah confirmed Sacred Valley trip after seeing the Inca Trail photos. Full payment received. Sent pre-trip preparation guide including altitude acclimatization tips.',
            ]
        );

        // Lead 7 - lost (Marc's Chadar trek - cancelled)
        Lead::updateOrCreate(
            ['trip_id' => $trips[6]->id],
            [
                'user_id' => $travellerMarc->id,
                'assigned_hct_id' => $hctCollab?->id,
                'stage' => 'lost',
                'enquiry_date' => Carbon::parse('2025-11-01'),
                'last_interaction_date' => Carbon::parse('2026-01-10'),
                'interaction_mode' => 'email',
                'reminder_delay_days' => 3,
                'notes' => 'Chadar trek cancelled due to insufficient river freezing. Marc was understanding. Offered to reschedule for Jan 2027 at same price. He said he will think about it.',
            ]
        );

        // Lead 8 - lost (Priya's Sikkim trip - price sensitivity)
        Lead::updateOrCreate(
            ['trip_id' => $trips[7]->id],
            [
                'user_id' => $travellerPriya->id,
                'assigned_hct_id' => $hctCollab?->id,
                'stage' => 'lost',
                'enquiry_date' => Carbon::parse('2026-01-05'),
                'last_interaction_date' => Carbon::parse('2026-02-01'),
                'interaction_mode' => 'call',
                'reminder_delay_days' => 5,
                'notes' => 'Priya and friends found the Sikkim trip slightly above their group budget. They are comparing with a local operator. Offered a 10% group discount but no response yet. Likely lost.',
            ]
        );

        // ─────────────────────────────────────────────────────────
        // 6. TRIP ITINERARIES — give a few trips real day-wise plans
        //    (idempotent via updateOrCreate). One trip is set to
        //    "completed" WITH experiences so review-eligibility passes.
        // ─────────────────────────────────────────────────────────

        // helper: attach a region + selected experiences + day-wise itinerary to a trip.
        $seedItinerary = function (?Trip $trip, int $regionId, array $expSlugs, array $dayPlan) {
            if (!$trip) return;
            $adults = max($trip->adults ?? 1, 1);

            TripRegion::updateOrCreate(['trip_id' => $trip->id, 'region_id' => $regionId], []);

            $expBySlug = Experience::whereIn('slug', $expSlugs)->get()->keyBy('slug');
            $sortOrder = 0;
            foreach ($expSlugs as $slug) {
                $exp = $expBySlug->get($slug);
                if (!$exp) continue;
                TripSelectedExperience::updateOrCreate(
                    ['trip_id' => $trip->id, 'experience_id' => $exp->id],
                    ['sort_order' => $sortOrder++]
                );
            }

            $dayNum = 0;
            foreach ($dayPlan as $plan) {
                $dayNum++;
                $exp = isset($plan['exp']) ? $expBySlug->get($plan['exp']) : null;
                $day = TripDay::updateOrCreate(
                    ['trip_id' => $trip->id, 'day_number' => $dayNum],
                    [
                        'date' => $trip->start_date ? Carbon::parse($trip->start_date)->addDays($dayNum - 1) : null,
                        'title' => $plan['title'] ?? ('Day ' . $dayNum),
                        'description' => $plan['description'] ?? null,
                        'day_type' => $plan['type'] ?? ($exp ? 'activity' : 'travel'),
                        'added_by' => 'system',
                        'is_experience_day' => $exp ? true : false,
                        'sort_order' => $dayNum - 1,
                    ]
                );
                if ($exp) {
                    TripDayExperience::updateOrCreate(
                        ['trip_day_id' => $day->id, 'experience_id' => $exp->id],
                        [
                            'start_time' => $plan['start'] ?? '08:00',
                            'end_time' => $plan['end'] ?? '18:00',
                            'cost_per_person' => $exp->base_cost_per_person,
                            'total_cost' => ($plan['charge'] ?? false) ? ($exp->base_cost_per_person * $adults) : 0,
                            'notes' => $plan['notes'] ?? null,
                            'sort_order' => 0,
                        ]
                    );
                }
            }
        };

        // Trip 2 — Annapurna (region 12): 4-day plan (stays not_confirmed)
        $seedItinerary($trips[1] ?? null, 12, ['annapurna-sunrise-trek', 'gurung-cultural-immersion'], [
            ['type' => 'arrival', 'title' => 'Arrival in Pokhara', 'description' => 'Arrive, acclimatise, gear check.'],
            ['exp' => 'annapurna-sunrise-trek', 'charge' => true, 'title' => 'Poon Hill Sunrise Trek begins', 'notes' => 'Drive to Nayapul, trek to Ulleri.', 'start' => '06:00', 'end' => '17:00'],
            ['exp' => 'annapurna-sunrise-trek', 'title' => 'Ghorepani to Poon Hill', 'notes' => 'Pre-dawn climb to Poon Hill (3,210 m) for sunrise over Annapurna & Dhaulagiri.', 'start' => '04:30', 'end' => '16:00'],
            ['exp' => 'gurung-cultural-immersion', 'charge' => true, 'title' => 'Gurung Village Cultural Day', 'notes' => 'Homestay with a Gurung family, traditional dinner and dances.', 'start' => '09:00', 'end' => '21:00'],
        ]);

        // Trip 3 — Spiti Valley (region 3): 5-day plan (stays confirmed)
        $seedItinerary($trips[2] ?? null, 3, ['spiti-monastery-circuit', 'pin-valley-snow-leopard-trail'], [
            ['type' => 'travel', 'title' => 'Manali to Kaza', 'description' => 'Long drive over Kunzum Pass into Spiti.'],
            ['exp' => 'spiti-monastery-circuit', 'charge' => true, 'title' => 'Spiti Monastery Circuit', 'notes' => 'Key, Tabo and Dhankar monasteries.', 'start' => '08:00', 'end' => '18:00'],
            ['exp' => 'spiti-monastery-circuit', 'title' => 'Kibber & Langza villages', 'notes' => 'High-altitude villages, fossil hunting at Langza.', 'start' => '08:30', 'end' => '17:30'],
            ['exp' => 'pin-valley-snow-leopard-trail', 'charge' => true, 'title' => 'Pin Valley Snow Leopard Trail', 'notes' => 'Trek into Pin Valley National Park with a wildlife tracker.', 'start' => '07:00', 'end' => '18:00'],
            ['type' => 'departure', 'title' => 'Kaza to Shimla', 'description' => 'Return drive via the Hindustan-Tibet road.'],
        ]);

        // Trip 4 — Tirthan Valley (region 1): COMPLETED with experiences (review-eligible)
        $seedItinerary($trips[3] ?? null, 1, ['tirthan-valley-homestay', 'great-himalayan-national-park-trek'], [
            ['exp' => 'tirthan-valley-homestay', 'charge' => true, 'title' => 'Tirthan Valley Homestay', 'notes' => 'Riverside homestay, trout fishing, local Himachali meals.', 'start' => '12:00', 'end' => '22:00'],
            ['exp' => 'great-himalayan-national-park-trek', 'charge' => true, 'title' => 'GHNP Day Trek', 'notes' => 'Guided trek into the buffer zone of the Great Himalayan National Park.', 'start' => '07:00', 'end' => '17:00'],
            ['type' => 'departure', 'title' => 'Departure from Aut', 'description' => 'Drive back to Aut for onward journey.'],
        ]);
        if (isset($trips[3])) {
            $trips[3]->update(['status' => 'completed', 'stage' => 'closed']);
        }

        // ─────────────────────────────────────────────────────────
        // 7. REGENERATIVE PROJECTS (4) — linked to real regions
        // ─────────────────────────────────────────────────────────

        $regenSeed = [
            [
                'name' => 'Tirthan River Trout Habitat Restoration',
                'region_slug' => 'tirthan-valley',
                'local_association' => 'Tirthan Conservation & Tourism Development Association',
                'action_type' => 'River & Aquatic Habitat',
                'short_description' => 'Restoring native trout spawning grounds and riparian buffers along the Tirthan river.',
                'detailed_description' => 'Removes invasive willow, replants native alder and bamboo on eroded banks, and funds community patrols against illegal fishing during the spawning season.',
                'impact_unit' => 'metres of riverbank restored',
                'measurement_frequency' => 'cumulative',
                'reference_budget' => 250000.00,
                'cost_per_impact_unit' => 500.00,
                'operational_constraints' => 'Planting only April–June and September–October; monsoon access limited.',
                'is_active' => true,
            ],
            [
                'name' => 'Spiti Cold-Desert Reforestation',
                'region_slug' => 'spiti-valley',
                'local_association' => 'Spiti Ecosphere',
                'action_type' => 'Reforestation',
                'short_description' => 'Planting hardy seabuckthorn and willow to stabilise Spiti\'s fragile cold-desert slopes.',
                'detailed_description' => 'Village nurseries raise saplings that are transplanted with drip irrigation; seabuckthorn berries also generate supplementary income for women\'s self-help groups.',
                'impact_unit' => 'trees planted & maintained',
                'measurement_frequency' => 'periodic',
                'reference_budget' => 400000.00,
                'cost_per_impact_unit' => 120.00,
                'operational_constraints' => 'Single short planting window in May–June; site frozen Nov–Mar.',
                'is_active' => true,
            ],
            [
                'name' => 'Ladakh Solar Cookstove Programme',
                'region_slug' => 'ladakh',
                'local_association' => 'Ladakh Ecological Development Group (LEDeG)',
                'action_type' => 'Clean Energy',
                'short_description' => 'Replacing kerosene and dung stoves with parabolic solar cookers in remote Ladakhi homestays.',
                'detailed_description' => 'Each installed unit offsets roughly one tonne of CO2 a year and cuts indoor air pollution; technicians train host families in maintenance.',
                'impact_unit' => 'solar cookers installed',
                'measurement_frequency' => 'one_time',
                'reference_budget' => 300000.00,
                'cost_per_impact_unit' => 8000.00,
                'operational_constraints' => 'Installation Jun–Sep only; many villages road-cut in winter.',
                'is_active' => true,
            ],
            [
                'name' => 'Annapurna Trail Waste & Plastic-Free Initiative',
                'region_slug' => 'annapurna',
                'local_association' => 'Annapurna Conservation Area Project (ACAP)',
                'action_type' => 'Waste Management',
                'short_description' => 'Funding waste segregation points, refill stations and porter clean-up crews along the Annapurna circuit.',
                'detailed_description' => 'Supports back-hauling of non-biodegradable waste, community recycling sheds in Ghorepani and Chhomrong, and water-refill stations to cut single-use plastic bottles.',
                'impact_unit' => 'kg of waste removed from the trail',
                'measurement_frequency' => 'cumulative',
                'reference_budget' => 350000.00,
                'cost_per_impact_unit' => 80.00,
                'operational_constraints' => 'Clean-up sweeps concentrated in spring and autumn trekking seasons.',
                'is_active' => true,
            ],
        ];
        foreach ($regenSeed as $rp) {
            $regionSlug = $rp['region_slug'];
            unset($rp['region_slug']);
            $region = Region::where('slug', $regionSlug)->first();
            if (!$region) continue;
            RegenerativeProject::updateOrCreate(
                ['name' => $rp['name']],
                array_merge($rp, ['region_id' => $region->id])
            );
        }

        // Link a couple of experiences to regenerative projects so the
        // detail-page "Regenerative Impact" block and the Impact tab have data.
        $rpTirthan = RegenerativeProject::where('name', 'Tirthan River Trout Habitat Restoration')->first();
        $rpSpiti = RegenerativeProject::where('name', 'Spiti Cold-Desert Reforestation')->first();
        if ($rpTirthan) {
            Experience::where('slug', 'tirthan-valley-homestay')->update(['regenerative_project_id' => $rpTirthan->id]);
            Experience::where('slug', 'great-himalayan-national-park-trek')->update(['regenerative_project_id' => $rpTirthan->id]);
        }
        if ($rpSpiti) {
            Experience::where('slug', 'spiti-monastery-circuit')->update(['regenerative_project_id' => $rpSpiti->id]);
        }

        // ─────────────────────────────────────────────────────────
        // 8. PDF TEMPLATES (additional — id 1 "itinerary_pdf" already exists)
        // ─────────────────────────────────────────────────────────

        PdfTemplate::updateOrCreate(
            ['key' => 'trip_voucher'],
            [
                'name' => 'Trip Confirmation Voucher',
                'header_html' => '<div style="text-align:center;font-family:sans-serif;"><h2 style="color:#79a09f;margin:0;">HECO</h2><p style="margin:2px 0;font-size:11px;color:#555;">Regenerative Travel — Trip Voucher</p></div>',
                'footer_html' => '<div style="text-align:center;font-size:9px;color:#888;">This voucher confirms your trip with HECO. Carry a copy during travel.</div>',
                'css' => 'body{font-family:sans-serif;color:#333;font-size:12px;} h1,h2,h3{color:#79a09f;}',
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'is_active' => true,
            ]
        );
        PdfTemplate::updateOrCreate(
            ['key' => 'payment_receipt'],
            [
                'name' => 'Payment Receipt',
                'header_html' => '<div style="text-align:center;font-family:sans-serif;"><h2 style="color:#79a09f;margin:0;">HECO</h2><p style="margin:2px 0;font-size:11px;color:#555;">Payment Receipt</p></div>',
                'footer_html' => '<div style="text-align:center;font-size:9px;color:#888;">Computer-generated receipt. GST included where applicable.</div>',
                'css' => 'body{font-family:sans-serif;color:#333;font-size:12px;} table{width:100%;border-collapse:collapse;} td,th{padding:6px;border:1px solid #ddd;}',
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'is_active' => true,
            ]
        );

        // ─────────────────────────────────────────────────────────
        // 9. TRAVELLER PAYMENTS — against confirmed trips
        // ─────────────────────────────────────────────────────────

        $tripLadakh = $trips[0] ?? null;   // HECO-T-0101, confirmed, final_price 57960
        $tripSpiti = $trips[2] ?? null;    // HECO-T-0103, confirmed, final_price 79695
        if ($tripLadakh) {
            TravellerPayment::updateOrCreate(
                ['trip_id' => $tripLadakh->id, 'razorpay_payment_id' => 'SEED-PAY-0101-A'],
                [
                    'user_id' => $tripLadakh->user_id,
                    'amount' => 25000.00,
                    'payment_date' => Carbon::parse('2026-03-01'),
                    'mode' => 'razorpay',
                    'payment_status' => 'paid',
                    'recorded_by' => $adminId,
                    'razorpay_order_id' => 'SEED-ORD-0101-A',
                    'notes' => 'Initial 25% advance payment.',
                ]
            );
            TravellerPayment::updateOrCreate(
                ['trip_id' => $tripLadakh->id, 'razorpay_payment_id' => 'SEED-PAY-0101-B'],
                [
                    'user_id' => $tripLadakh->user_id,
                    'amount' => 32960.00,
                    'payment_date' => Carbon::parse('2026-04-15'),
                    'mode' => 'razorpay',
                    'payment_status' => 'paid',
                    'recorded_by' => $adminId,
                    'razorpay_order_id' => 'SEED-ORD-0101-B',
                    'notes' => 'Balance payment — trip fully paid.',
                ]
            );
        }
        if ($tripSpiti) {
            TravellerPayment::updateOrCreate(
                ['trip_id' => $tripSpiti->id, 'razorpay_payment_id' => 'SEED-PAY-0103-A'],
                [
                    'user_id' => $tripSpiti->user_id,
                    'amount' => 30000.00,
                    'payment_date' => Carbon::parse('2026-05-02'),
                    'mode' => 'bank_transfer',
                    'payment_status' => 'paid',
                    'recorded_by' => $adminId,
                    'notes' => 'Partial payment received via NEFT.',
                ]
            );
            TravellerPayment::updateOrCreate(
                ['trip_id' => $tripSpiti->id, 'razorpay_payment_id' => 'SEED-PAY-0103-B'],
                [
                    'user_id' => $tripSpiti->user_id,
                    'amount' => 15000.00,
                    'payment_date' => Carbon::parse('2026-05-08'),
                    'mode' => 'razorpay',
                    'payment_status' => 'pending',
                    'recorded_by' => $adminId,
                    'razorpay_order_id' => 'SEED-ORD-0103-B',
                    'notes' => 'Payment initiated, awaiting confirmation.',
                ]
            );
        }
    }

    /**
     * Helper to create a user and associated service provider.
     * Returns the ServiceProvider model instance.
     */
    private function createProviderWithUser(array $userAttrs, array $providerAttrs, ?int $adminId): ServiceProvider
    {
        $user = User::firstOrCreate(
            ['email' => $userAttrs['email']],
            [
                'full_name' => $userAttrs['full_name'],
                'password' => Hash::make('password'),
                'auth_type' => 'email',
                'user_role' => $userAttrs['user_role'],
                'mobile' => $userAttrs['mobile'] ?? null,
                'status' => 'active',
            ]
        );

        // Look up region by slug
        $regionSlug = $providerAttrs['region_slug'] ?? null;
        unset($providerAttrs['region_slug']);

        $region = $regionSlug ? Region::where('slug', $regionSlug)->first() : null;

        $providerData = array_merge($providerAttrs, [
            'user_id' => $user->id,
            'region_id' => $region?->id ?? 1,
        ]);

        // Set approved_at for approved providers
        if ($providerData['status'] === 'approved' && $adminId) {
            $providerData['approved_at'] = $providerData['approved_at'] ?? now();
            $providerData['approved_by'] = $adminId;
        }

        return ServiceProvider::updateOrCreate(
            ['email' => $providerAttrs['email']],
            $providerData
        );
    }
}
