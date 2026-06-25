<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $location = fake()->randomElement([
            ['region' => 'Hamburg', 'postal_code' => '20095', 'country_code' => 'DE'],
            ['region' => 'Hannover', 'postal_code' => '30159', 'country_code' => 'DE'],
            ['region' => 'München', 'postal_code' => '80331', 'country_code' => 'DE'],
            ['region' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE'],
        ]);

        return [
            'owner_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->bothify('####'),
            'description' => fake()->paragraph(),
            'region' => $location['region'],
            'postal_code' => $location['postal_code'],
            'country_code' => $location['country_code'],
            'visibility' => Group::VISIBILITY_PUBLIC,
            'status' => Group::STATUS_ACTIVE,
        ];
    }
}
