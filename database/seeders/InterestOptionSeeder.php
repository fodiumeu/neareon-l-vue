<?php

namespace Database\Seeders;

use App\Models\InterestOption;
use Illuminate\Database\Seeder;

class InterestOptionSeeder extends Seeder
{
    /**
     * Seed the MVP interest option catalog.
     */
    public function run(): void
    {
        $interests = [
            ['slug' => 'music', 'label' => 'Musik'],
            ['slug' => 'sport', 'label' => 'Sport'],
            ['slug' => 'gaming', 'label' => 'Gaming'],
            ['slug' => 'travel', 'label' => 'Reisen'],
            ['slug' => 'movies', 'label' => 'Filme'],
            ['slug' => 'series', 'label' => 'Serien'],
            ['slug' => 'cooking', 'label' => 'Kochen'],
            ['slug' => 'fitness', 'label' => 'Fitness'],
            ['slug' => 'fashion', 'label' => 'Mode'],
            ['slug' => 'photography', 'label' => 'Fotografie'],
            ['slug' => 'reading', 'label' => 'Lesen'],
            ['slug' => 'technology', 'label' => 'Technologie'],
            ['slug' => 'culture', 'label' => 'Kultur'],
            ['slug' => 'languages', 'label' => 'Sprachen'],
            ['slug' => 'family', 'label' => 'Familie'],
            ['slug' => 'health', 'label' => 'Gesundheit'],
            ['slug' => 'business-networking', 'label' => 'Business / Networking'],
            ['slug' => 'learning', 'label' => 'Lernen'],
            ['slug' => 'food-going-out', 'label' => 'Essen & Ausgehen'],
            ['slug' => 'nature', 'label' => 'Natur'],
            ['slug' => 'events', 'label' => 'Events'],
            ['slug' => 'community', 'label' => 'Community'],
            ['slug' => 'heritage', 'label' => 'Herkunft / Heritage'],
            ['slug' => 'volunteering', 'label' => 'Ehrenamt'],
            ['slug' => 'creativity', 'label' => 'Kreativität'],
        ];

        foreach ($interests as $index => $interest) {
            InterestOption::query()->updateOrCreate(
                ['slug' => $interest['slug']],
                [
                    'label' => $interest['label'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
