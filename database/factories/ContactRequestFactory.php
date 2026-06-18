<?php

namespace Database\Factories;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'message' => fake()->optional()->text(250),
            'status' => ContactRequestStatus::Pending,
            'responded_at' => null,
        ];
    }
}
