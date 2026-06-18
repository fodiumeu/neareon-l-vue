<?php

use App\Enums\ProfileVisibility;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Models\User;
use App\Support\OnboardingOptions;
use Database\Seeders\InterestOptionSeeder;
use Database\Seeders\LanguageOptionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        LanguageOptionSeeder::class,
        InterestOptionSeeder::class,
    ]);

    Profile::created(fn (Profile $profile) => attachManagedProfileOptionsFromJson($profile));
});

test('guests cannot open onboarding', function () {
    $this->get(route('onboarding.create'))
        ->assertRedirect(route('login'));
});

test('users without a profile are redirected to onboarding details', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('onboarding.details'));
});

test('users with details but without interests are redirected to onboarding interests', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('onboarding.interests'));
});

test('json values without pivots do not complete onboarding steps', function () {
    $user = User::factory()->create();
    Profile::withoutEvents(fn () => Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => ['Deutsch'],
    ]));

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('onboarding.interests'));
});

test('users with details and interests but without languages are redirected to onboarding languages', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('onboarding.languages'));
});

test('users with complete onboarding are redirected to dashboard', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => ['Deutsch'],
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('dashboard'));
});

test('direct later onboarding steps redirect to the correct previous step', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.interests'))
        ->assertRedirect(route('onboarding.details'));

    $this->actingAs($user)
        ->get(route('onboarding.languages'))
        ->assertRedirect(route('onboarding.details'));

    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.languages'))
        ->assertRedirect(route('onboarding.interests'));
});

test('completed users are redirected away from onboarding steps', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => ['Deutsch'],
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.details'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('onboarding.interests'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get(route('onboarding.languages'))
        ->assertRedirect(route('dashboard'));
});

test('details step can be opened by users without a profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.details'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding/Details'),
        );
});

test('details step creates a profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.details.store'), [
            'username' => 'new_member',
            'display_name' => 'New Member',
        ])
        ->assertRedirect(route('onboarding.interests'));

    expect($user->fresh()->profile)
        ->not->toBeNull()
        ->username->toBe('new_member')
        ->display_name->toBe('New Member');
});

test('details step creates profile with public visibility defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.details.store'), [
            'username' => 'public_defaults',
            'display_name' => 'Public Defaults',
        ])
        ->assertRedirect(route('onboarding.interests'));

    $profile = $user->fresh()->profile;

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Public);
});

test('duplicate usernames are blocked in details step', function () {
    Profile::factory()->create([
        'username' => 'taken_name',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('onboarding.details'))
        ->post(route('onboarding.details.store'), [
            'username' => 'taken_name',
            'display_name' => 'Taken Name',
        ])
        ->assertRedirect(route('onboarding.details'))
        ->assertSessionHasErrors('username');

    expect(Profile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('usernames are normalized in details step', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.details.store'), [
            'username' => '  Mixed_Name-42  ',
            'display_name' => 'Mixed Name',
        ])
        ->assertRedirect(route('onboarding.interests'));

    expect($user->fresh()->profile->username)->toBe('mixed_name-42');
});

test('users cannot create a second profile through details step', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'username' => 'first_profile',
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.details.store'), [
            'username' => 'second_profile',
            'display_name' => 'Second Profile',
        ])
        ->assertRedirect(route('onboarding.interests'));

    expect(Profile::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fresh()->profile->username)->toBe('first_profile');
});

test('interests step stores selected interests as an array', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.interests.store'), [
            'interests' => ['Musik', 'Events'],
        ])
        ->assertRedirect(route('onboarding.languages'));

    expect($profile->refresh()->interests)->toBe(['Musik', 'Events'])
        ->and($profile->interestOptions()->pluck('slug')->sort()->values()->all())
        ->toBe(['events', 'music']);
});

test('interests step shows only active managed options in configured order', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);
    InterestOption::query()->where('slug', 'music')->update(['sort_order' => 100]);
    InterestOption::query()->where('slug', 'events')->update(['sort_order' => 1]);
    InterestOption::query()->where('slug', 'sport')->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('onboarding.interests'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('interests.0', 'Events')
            ->where('interests.23', 'Musik')
            ->where('interests', fn ($interests) => ! $interests->contains('Sport')),
        );
});

test('interests step requires at least one interest', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.interests'))
        ->post(route('onboarding.interests.store'), [
            'interests' => [],
        ])
        ->assertRedirect(route('onboarding.interests'))
        ->assertSessionHasErrors('interests');
});

test('interests step rejects more than twenty interests', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.interests'))
        ->post(route('onboarding.interests.store'), [
            'interests' => array_merge(OnboardingOptions::interests(), ['Extra']),
        ])
        ->assertRedirect(route('onboarding.interests'))
        ->assertSessionHasErrors('interests');
});

test('interests step rejects unavailable interests', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.interests'))
        ->post(route('onboarding.interests.store'), [
            'interests' => ['Nicht vorhanden'],
        ])
        ->assertRedirect(route('onboarding.interests'))
        ->assertSessionHasErrors('interests.0');
});

test('interests step rejects inactive managed options', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);
    InterestOption::query()->where('slug', 'music')->update(['is_active' => false]);

    $this->actingAs($user)
        ->from(route('onboarding.interests'))
        ->post(route('onboarding.interests.store'), [
            'interests' => ['Musik'],
        ])
        ->assertRedirect(route('onboarding.interests'))
        ->assertSessionHasErrors('interests.0');
});

test('interests step removes duplicates', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'interests' => null,
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.interests.store'), [
            'interests' => ['Musik', 'Musik', 'Events'],
        ])
        ->assertRedirect(route('onboarding.languages'));

    expect($profile->refresh()->interests)->toBe(['Musik', 'Events']);
    expect($profile->interestOptions()->count())->toBe(2);
});

test('languages step stores languages as an array', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Deutsch', 'Englisch'],
        ])
        ->assertRedirect(route('dashboard'));

    $profile->refresh();

    expect($profile->languages)->toBe(['Deutsch', 'Englisch'])
        ->and($profile->languageOptions()->pluck('code')->all())
        ->toBe(['de', 'en'])
        ->and($profile->languageOptions()->get()->pluck('pivot.position')->all())
        ->toBe([1, 2]);
});

test('languages step shows only active managed options in configured order', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);
    LanguageOption::query()->where('code', 'de')->update(['sort_order' => 100]);
    LanguageOption::query()->where('code', 'hr')->update(['sort_order' => 1]);
    LanguageOption::query()->where('code', 'en')->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('onboarding.languages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('languages.0', 'Kroatisch')
            ->where('languages.19', 'Deutsch')
            ->where('languages', fn ($languages) => ! $languages->contains('Englisch')),
        );
});

test('languages step requires a main language', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.languages'))
        ->post(route('onboarding.languages.store'), [
            'languages' => [],
        ])
        ->assertRedirect(route('onboarding.languages'))
        ->assertSessionHasErrors('languages');
});

test('languages step rejects more than five languages', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.languages'))
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Deutsch', 'Englisch', 'Türkisch', 'Arabisch', 'Spanisch', 'Italienisch'],
        ])
        ->assertRedirect(route('onboarding.languages'))
        ->assertSessionHasErrors('languages');
});

test('languages step rejects duplicate languages', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.languages'))
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Deutsch', 'Deutsch'],
        ])
        ->assertRedirect(route('onboarding.languages'))
        ->assertSessionHasErrors('languages.0');
});

test('languages step rejects unavailable languages', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->from(route('onboarding.languages'))
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Klingonisch'],
        ])
        ->assertRedirect(route('onboarding.languages'))
        ->assertSessionHasErrors('languages.0');
});

test('languages step rejects inactive managed options', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);
    LanguageOption::query()->where('code', 'de')->update(['is_active' => false]);

    $this->actingAs($user)
        ->from(route('onboarding.languages'))
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Deutsch'],
        ])
        ->assertRedirect(route('onboarding.languages'))
        ->assertSessionHasErrors('languages.0');
});

test('languages step keeps first language as main language', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.languages.store'), [
            'languages' => ['Englisch', 'Deutsch'],
        ])
        ->assertRedirect(route('dashboard'));

    expect($profile->refresh()->languages[0])->toBe('Englisch');
});

test('incomplete onboarding cannot access app areas', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.languages'));

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertRedirect(route('onboarding.languages'));

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertRedirect(route('onboarding.languages'));
});

test('complete onboarding can access app areas', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'interests' => ['Musik'],
        'languages' => ['Deutsch'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk();
});
