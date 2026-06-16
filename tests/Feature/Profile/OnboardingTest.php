<?php

use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open onboarding', function () {
    $this->get(route('onboarding.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users without a profile can open onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding'),
        );
});

test('authenticated users with a profile are redirected away from onboarding', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('dashboard'));
});

test('authenticated users without a profile can create a minimal profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'username' => 'new_member',
            'display_name' => 'New Member',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->profile)
        ->not->toBeNull()
        ->username->toBe('new_member')
        ->display_name->toBe('New Member');
});

test('successful onboarding creates exactly one profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'username' => 'single_profile',
            'display_name' => 'Single Profile',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Profile::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('duplicate usernames are blocked', function () {
    Profile::factory()->create([
        'username' => 'taken_name',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('onboarding.create'))
        ->post(route('onboarding.store'), [
            'username' => 'taken_name',
            'display_name' => 'Taken Name',
        ])
        ->assertRedirect(route('onboarding.create'))
        ->assertSessionHasErrors('username');

    expect(Profile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('usernames are normalized before storage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'username' => '  Mixed_Name-42  ',
            'display_name' => 'Mixed Name',
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->profile->username)->toBe('mixed_name-42');
});

test('invalid username characters are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('onboarding.create'))
        ->post(route('onboarding.store'), [
            'username' => 'bad name',
            'display_name' => 'Bad Name',
        ])
        ->assertRedirect(route('onboarding.create'))
        ->assertSessionHasErrors('username');

    expect(Profile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('users cannot create a second profile through onboarding', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'username' => 'first_profile',
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.store'), [
            'username' => 'second_profile',
            'display_name' => 'Second Profile',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Profile::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fresh()->profile->username)->toBe('first_profile');
});

test('dashboard redirects users without a profile to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

test('users with a profile can visit the dashboard', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard'),
        );
});
