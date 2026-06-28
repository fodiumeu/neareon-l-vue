<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(4, true);
        $startsAt = now()
            ->addDays(fake()->numberBetween(1, 90))
            ->setTime(
                fake()->numberBetween(8, 20),
                fake()->randomElement([0, 15, 30, 45]),
            );
        $location = fake()->randomElement([
            ['region' => 'Hamburg', 'postal_code' => '20095', 'country_code' => 'DE'],
            ['region' => 'Hannover', 'postal_code' => '30159', 'country_code' => 'DE'],
            ['region' => 'München', 'postal_code' => '80331', 'country_code' => 'DE'],
            ['region' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE'],
        ]);

        return [
            'owner_id' => User::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->bothify('####'),
            'description' => fake()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => fake()->boolean(70)
                ? $startsAt->copy()->addHours(fake()->numberBetween(1, 6))
                : null,
            'region' => $location['region'],
            'postal_code' => $location['postal_code'],
            'country_code' => $location['country_code'],
            'visibility' => Event::VISIBILITY_PUBLIC,
            'status' => Event::STATUS_ACTIVE,
            'max_attendees' => fake()->optional(0.5)->numberBetween(5, 250),
        ];
    }
}
