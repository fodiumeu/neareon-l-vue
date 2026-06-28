<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendee>
 */
class EventAttendeeFactory extends Factory
{
    protected $model = EventAttendee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => EventAttendee::STATUS_ACTIVE,
            'joined_at' => now(),
        ];
    }

    /**
     * Mark the attendance as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => EventAttendee::STATUS_PENDING,
            'joined_at' => null,
        ]);
    }
}
