<?php

namespace Database\Factories;

use App\Enums\ContactPermission;
use App\Enums\FollowPermission;
use App\Enums\MessagePermission;
use App\Enums\OnlineStatusVisibility;
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
            'follow_permission' => FollowPermission::Everyone,
            'contact_permission' => ContactPermission::Everyone,
            'message_permission' => MessagePermission::ExistingConversations,
            'online_status_visibility' => OnlineStatusVisibility::MutualContacts,
            'interests_visibility' => ProfileVisibility::Public,
            'languages_visibility' => ProfileVisibility::Public,
            'region_visibility' => ProfileVisibility::Public,
            'social_counts_visibility' => ProfileVisibility::Public,
        ];
    }
}
