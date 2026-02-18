<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            // India
            ['name' => 'Tirthan Valley', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 31.6380, 'longitude' => 77.4480, 'description' => 'A pristine valley in the Great Himalayan National Park buffer zone, known for trout fishing and nature walks.'],
            ['name' => 'Jibhi', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 31.6135, 'longitude' => 77.3420, 'description' => 'A charming hamlet surrounded by dense forests, waterfalls, and ancient temples.'],
            ['name' => 'Spiti Valley', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 32.2460, 'longitude' => 78.0350, 'description' => 'A cold desert mountain valley high in the Himalayas, with ancient monasteries and dramatic landscapes.'],
            ['name' => 'Kinnaur', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 31.5840, 'longitude' => 78.1740, 'description' => 'The land of gods, known for apple orchards, ancient temples, and the stunning Kinnaur Kailash.'],
            ['name' => 'Ladakh', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 34.1526, 'longitude' => 77.5770, 'description' => 'The land of high passes, known for Buddhist monasteries, stark landscapes, and vibrant culture.'],
            ['name' => 'Zanskar', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 33.5000, 'longitude' => 77.0000, 'description' => 'Remote valley known for the frozen river trek (Chadar), ancient monasteries, and pristine wilderness.'],
            ['name' => 'Kumaon', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 29.5900, 'longitude' => 79.6500, 'description' => 'Hill stations, dense forests, and rich cultural heritage in the foothills of the Himalayas.'],
            ['name' => 'Garhwal', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 30.3460, 'longitude' => 79.0600, 'description' => 'Home to sacred rivers, char dham pilgrimage, and breathtaking Himalayan meadows.'],
            ['name' => 'Sikkim', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 27.5330, 'longitude' => 88.5122, 'description' => 'A biodiversity hotspot with stunning views of Kanchenjunga, Buddhist monasteries, and rich culture.'],
            ['name' => 'Arunachal Pradesh', 'continent' => 'Asia', 'country' => 'India', 'latitude' => 28.2180, 'longitude' => 94.7278, 'description' => 'The land of rising sun, with diverse tribal cultures, pristine forests, and unexplored Himalayan trails.'],

            // Nepal
            ['name' => 'Everest Region', 'continent' => 'Asia', 'country' => 'Nepal', 'latitude' => 27.9881, 'longitude' => 86.9250, 'description' => 'Home to the world\'s highest peak, with legendary trekking routes, Sherpa culture, and breathtaking mountain vistas.'],
            ['name' => 'Annapurna', 'continent' => 'Asia', 'country' => 'Nepal', 'latitude' => 28.5960, 'longitude' => 83.8200, 'description' => 'A diverse trekking region with subtropical forests, alpine meadows, and stunning sunrise views from Poon Hill.'],
            ['name' => 'Langtang', 'continent' => 'Asia', 'country' => 'Nepal', 'latitude' => 28.2137, 'longitude' => 85.5150, 'description' => 'The valley of glaciers, known for Tamang culture, cheese factories, and accessible high-altitude trekking.'],

            // Bhutan
            ['name' => 'Paro Valley', 'continent' => 'Asia', 'country' => 'Bhutan', 'latitude' => 27.4286, 'longitude' => 89.4164, 'description' => 'Gateway to Bhutan featuring the iconic Tiger\'s Nest monastery, ancient dzongs, and pristine mountain scenery.'],
            ['name' => 'Bumthang', 'continent' => 'Asia', 'country' => 'Bhutan', 'latitude' => 27.5500, 'longitude' => 90.7300, 'description' => 'The spiritual heartland of Bhutan with sacred temples, alpine valleys, and traditional Bhutanese farming communities.'],

            // Peru
            ['name' => 'Sacred Valley', 'continent' => 'South America', 'country' => 'Peru', 'latitude' => -13.3320, 'longitude' => -72.0800, 'description' => 'The heartland of the Inca Empire, with ancient ruins, vibrant Andean communities, and the gateway to Machu Picchu.'],
            ['name' => 'Colca Canyon', 'continent' => 'South America', 'country' => 'Peru', 'latitude' => -15.6080, 'longitude' => -71.8860, 'description' => 'One of the world\'s deepest canyons, home to Andean condors, pre-Inca terraces, and traditional communities.'],

            // Tanzania
            ['name' => 'Kilimanjaro Region', 'continent' => 'Africa', 'country' => 'Tanzania', 'latitude' => -3.0674, 'longitude' => 37.3556, 'description' => 'Africa\'s highest peak surrounded by diverse ecosystems, Chagga coffee farms, and community-based conservation areas.'],

            // New Zealand
            ['name' => 'Fiordland', 'continent' => 'Oceania', 'country' => 'New Zealand', 'latitude' => -45.4146, 'longitude' => 167.7180, 'description' => 'UNESCO World Heritage wilderness with dramatic fjords, ancient rainforests, and the renowned Milford Track.'],

            // Norway
            ['name' => 'Lofoten Islands', 'continent' => 'Europe', 'country' => 'Norway', 'latitude' => 68.2350, 'longitude' => 14.5630, 'description' => 'Arctic archipelago with dramatic peaks, traditional fishing villages, northern lights, and midnight sun.'],
        ];

        foreach ($regions as $index => $region) {
            Region::updateOrCreate(
                ['slug' => Str::slug($region['name'])],
                array_merge($region, [
                    'slug' => Str::slug($region['name']),
                    'is_active' => true,
                    'sort_order' => $index,
                ])
            );
        }
    }
}
