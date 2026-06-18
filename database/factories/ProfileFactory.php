<?php

namespace Database\Factories;

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'username' => fake()->unique()->bothify('user_########'),
            'display_name' => fake()->name(),
            'bio' => fake()->sentence(),
            'region' => fake()->city(),
            'profile_visibility' => ProfileVisibility::Public,
            'interests_visibility' => ProfileVisibility::Public,
            'languages_visibility' => ProfileVisibility::Public,
            'region_visibility' => ProfileVisibility::Public,
            'social_counts_visibility' => ProfileVisibility::Public,
        ];
    }
}
