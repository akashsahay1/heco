<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Experience;
use App\Models\Review;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'traveller@hecoapp.com')->first();
        if (!$user) {
            echo "User not found.\n";
            return;
        }

        // Delete all reviews not from this user
        $deleted = Review::where('user_id', '!=', $user->id)->delete();
        echo "Deleted {$deleted} reviews from other accounts.\n";

        $experiences = Experience::where('is_active', true)->get();

        $reviews = [
            ['rating' => 5, 'title' => 'Absolutely breathtaking!', 'body' => 'One of the best experiences of my life. The scenery was stunning, the guides were fantastic, and every moment felt special. Would do it again in a heartbeat.'],
            ['rating' => 4, 'title' => 'Great cultural immersion', 'body' => 'Loved learning about the local traditions and way of life. The food was delicious and the hospitality was warm. A truly memorable trip.'],
            ['rating' => 3, 'title' => 'Good but could be improved', 'body' => 'The experience itself was enjoyable and the location was beautiful. However, the logistics could have been better organized. Still worth trying if you are in the area.'],
            ['rating' => 5, 'title' => 'Life-changing journey', 'body' => 'I came expecting a nice trip but left with a completely new perspective on life. The community interactions were genuine and heartwarming. This is responsible tourism at its finest.'],
            ['rating' => 2, 'title' => 'Not what I expected', 'body' => 'The description sounded amazing but the reality was a bit different. The weather did not cooperate and some activities were cancelled. The staff tried their best though.'],
            ['rating' => 4, 'title' => 'Highly recommended for nature lovers', 'body' => 'If you love nature and want to get off the beaten path, this is for you. The biodiversity was incredible and our guide knew every plant and bird by name.'],
            ['rating' => 5, 'title' => 'Pure magic in the mountains', 'body' => 'From sunrise to sunset, every moment was filled with wonder. The team went above and beyond to make us feel welcome. Already planning my next visit!'],
            ['rating' => 3, 'title' => 'Decent experience overall', 'body' => 'It was a pleasant trip with some memorable moments. The accommodation was basic but comfortable. Would recommend for adventurous travellers who do not mind roughing it a bit.'],
            ['rating' => 4, 'title' => 'A wonderful Himalayan experience', 'body' => 'This was truly an unforgettable experience. The local guides were incredibly knowledgeable and the scenery was breathtaking. Highly recommend for anyone looking to connect with nature and local culture.'],
            ['rating' => 5, 'title' => 'Exceeded all expectations', 'body' => 'We were blown away by the beauty and the warmth of the people. The regenerative aspect of the trip made it even more meaningful knowing we were contributing positively.'],
        ];

        $created = 0;
        foreach ($experiences as $index => $experience) {
            $existing = Review::where('user_id', $user->id)->where('experience_id', $experience->id)->first();
            if ($existing) {
                echo "  Skipped (exists): {$experience->name}\n";
                continue;
            }

            $reviewData = $reviews[$index % count($reviews)];

            Review::create([
                'user_id' => $user->id,
                'experience_id' => $experience->id,
                'rating' => $reviewData['rating'],
                'title' => $reviewData['title'],
                'body' => $reviewData['body'],
            ]);

            echo "  Created: '{$experience->name}' - {$reviewData['rating']}/5\n";
            $created++;
        }

        echo "\nDone! Created {$created} reviews. All from {$user->full_name}.\n";
    }
}
