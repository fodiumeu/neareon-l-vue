<?php

use App\Enums\ProfileVisibility;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    LanguageOption::query()->create([
        'code' => 'de',
        'label' => 'Deutsch',
        'native_label' => 'Deutsch',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    LanguageOption::query()->create([
        'code' => 'en',
        'label' => 'Englisch',
        'native_label' => 'English',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    LanguageOption::query()->create([
        'code' => 'es',
        'label' => 'Spanisch',
        'native_label' => 'Español',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    foreach ([
        'music' => 'Musik',
        'events' => 'Events',
        'community' => 'Community',
        'technology' => 'Technik',
    ] as $slug => $label) {
        InterestOption::query()->create([
            'slug' => $slug,
            'label' => $label,
            'sort_order' => match ($slug) {
                'music' => 1,
                'events' => 2,
                'community' => 3,
                'technology' => 4,
            },
            'is_active' => true,
        ]);
    }

    Profile::created(fn (Profile $profile) => attachManagedProfileOptionsFromJson($profile));
});

function validProfileUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Updated Member',
        'bio' => 'Eine kurze aktualisierte Info.',
        'region' => 'Hamburg',
        'languages' => ['de', 'en'],
        'interests' => ['music', 'events'],
        'profile_visibility' => ProfileVisibility::Public->value,
        'interests_visibility' => ProfileVisibility::Public->value,
        'languages_visibility' => ProfileVisibility::Public->value,
        'region_visibility' => ProfileVisibility::Mutuals->value,
        'social_counts_visibility' => ProfileVisibility::Public->value,
    ], $overrides);
}

test('guests cannot open profile editing', function () {
    $this->get(route('neareon-profile.edit'))
        ->assertRedirect(route('login'));
});

test('users without a profile are redirected to onboarding from profile editing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertRedirect(route('onboarding.details'));
});

test('users with a profile can open profile editing', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'display_name' => 'Existing Member',
        'bio' => 'Existing Bio',
        'languages' => ['Deutsch', 'Englisch'],
        'interests' => ['Musik', 'Events'],
        'profile_visibility' => ProfileVisibility::Mutuals,
        'interests_visibility' => ProfileVisibility::Private,
        'languages_visibility' => ProfileVisibility::Public,
        'region_visibility' => ProfileVisibility::Private,
        'social_counts_visibility' => ProfileVisibility::Mutuals,
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.display_name', 'Existing Member')
            ->where('profile.bio', 'Existing Bio')
            ->where('profile.languages', ['de', 'en'])
            ->where('profile.interests', ['music', 'events'])
            ->where('languageOptions', [
                ['value' => 'de', 'label' => 'Deutsch', 'is_active' => true],
                ['value' => 'en', 'label' => 'Englisch (English)', 'is_active' => true],
                ['value' => 'es', 'label' => 'Spanisch (Español)', 'is_active' => true],
            ])
            ->where('interestOptions', [
                ['value' => 'music', 'label' => 'Musik', 'is_active' => true],
                ['value' => 'events', 'label' => 'Events', 'is_active' => true],
                ['value' => 'community', 'label' => 'Community', 'is_active' => true],
                ['value' => 'technology', 'label' => 'Technik', 'is_active' => true],
            ])
            ->where('profile.profile_visibility', 'mutuals')
            ->where('profile.interests_visibility', 'private')
            ->where('profile.languages_visibility', 'public')
            ->where('profile.region_visibility', 'private')
            ->where('profile.social_counts_visibility', 'mutuals'),
        );
});

test('profile editing exposes followers only for field visibility options', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileVisibilityOptions', [
                ['value' => 'public', 'label' => 'Alle'],
                ['value' => 'mutuals', 'label' => 'Gegenseitige Kontakte'],
                ['value' => 'private', 'label' => 'Nur ich'],
            ])
            ->where('fieldVisibilityOptions', [
                ['value' => 'public', 'label' => 'Alle'],
                ['value' => 'followers', 'label' => 'Follower'],
                ['value' => 'mutuals', 'label' => 'Gegenseitige Kontakte'],
                ['value' => 'private', 'label' => 'Nur ich'],
            ]),
        );
});

test('users can update display name bio and region', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'display_name' => 'Neuer Anzeigename',
            'bio' => 'Neue Kurzinfo.',
            'region' => 'Koeln',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->display_name)->toBe('Neuer Anzeigename')
        ->and($profile->bio)->toBe('Neue Kurzinfo.')
        ->and($profile->region)->toBe('Koeln');
});

test('saved bio is returned when reopening profile editing', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create([
        'bio' => 'Gespeicherte Bio.',
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.bio', 'Gespeicherte Bio.'),
        );
});

test('changing visibility keeps an existing bio when bio is submitted unchanged', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'bio' => 'Bleibende Bio.',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'display_name' => $profile->display_name,
            'bio' => 'Bleibende Bio.',
            'region' => $profile->region,
            'languages' => implode(', ', $profile->languages ?? []),
            'interests' => implode(', ', $profile->interests ?? []),
            'region_visibility' => ProfileVisibility::Private->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->bio)->toBe('Bleibende Bio.')
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Private);
});

test('missing bio input does not clear an existing bio', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'bio' => 'Nicht versehentlich löschen.',
    ]);
    $payload = validProfileUpdatePayload([
        'display_name' => $profile->display_name,
        'region' => $profile->region,
        'languages' => implode(', ', $profile->languages ?? []),
        'interests' => implode(', ', $profile->interests ?? []),
        'region_visibility' => ProfileVisibility::Private->value,
    ]);
    unset($payload['bio']);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), $payload)
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->bio)->toBe('Nicht versehentlich löschen.');
});

test('users can intentionally clear their bio', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'bio' => 'Diese Bio wird geleert.',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'bio' => '',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->bio)->toBeNull();
});

test('users can update languages and interests', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $profile->languageOptions()->detach();
    $profile->interestOptions()->detach();
    $profile->languageOptions()->attach(
        LanguageOption::query()->where('code', 'es')->firstOrFail(),
        ['position' => 1],
    );
    $profile->languageOptions()->attach(
        LanguageOption::query()->where('code', 'de')->firstOrFail(),
        ['position' => 2],
    );
    $profile->interestOptions()->attach(
        InterestOption::query()->where('slug', 'music')->firstOrFail(),
    );

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => ['de', 'en', 'es'],
            'interests' => ['community', 'technology'],
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => ['de', 'en', 'es'],
            'interests' => ['community', 'technology'],
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->languages)->toBe(['de', 'en', 'es'])
        ->and($profile->interests)->toBe(['community', 'technology'])
        ->and($profile->languageOptions()->pluck('code')->all())
        ->toBe(['de', 'en', 'es'])
        ->and($profile->languageOptions()->get()->pluck('pivot.position')->all())
        ->toBe([1, 2, 3])
        ->and($profile->interestOptions()->pluck('slug')->sort()->values()->all())
        ->toBe(['community', 'technology'])
        ->and($profile->languageOptions()->count())->toBe(3)
        ->and($profile->interestOptions()->count())->toBe(2);
});

test('comma separated languages and interests are stored as arrays', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => 'de, en, , de',
            'interests' => 'music, events, technology',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->languages)->toBe(['de', 'en'])
        ->and($profile->interests)->toBe(['music', 'events', 'technology'])
        ->and($profile->languageOptions()->pluck('code')->all())
        ->toBe(['de', 'en'])
        ->and($profile->interestOptions()->count())->toBe(3);
});

test('inactive options are only exposed when already selected', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'languages' => ['Deutsch', 'Latein'],
        'interests' => ['Musik', 'Ehemaliges Thema'],
    ]);
    LanguageOption::query()->create([
        'code' => 'la',
        'label' => 'Latein',
        'native_label' => null,
        'sort_order' => 4,
        'is_active' => false,
    ]);
    InterestOption::query()->create([
        'slug' => 'former-topic',
        'label' => 'Ehemaliges Thema',
        'sort_order' => 5,
        'is_active' => false,
    ]);
    attachManagedProfileOptionsFromJson($profile);
    LanguageOption::query()->create([
        'code' => 'xx',
        'label' => 'Nicht gewählt',
        'native_label' => null,
        'sort_order' => 5,
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('languageOptions.3', [
                'value' => 'la',
                'label' => 'Latein',
                'is_active' => false,
            ])
            ->where('interestOptions.4', [
                'value' => 'former-topic',
                'label' => 'Ehemaliges Thema',
                'is_active' => false,
            ])
            ->missing('languageOptions.4'),
        );
});

test('legacy labels are normalized to stable option keys when saved', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'languages' => ['Deutsch', 'Latein'],
        'interests' => ['Musik', 'Ehemaliges Thema'],
    ]);
    LanguageOption::query()->create([
        'code' => 'la',
        'label' => 'Latein',
        'native_label' => null,
        'sort_order' => 4,
        'is_active' => false,
    ]);
    InterestOption::query()->create([
        'slug' => 'former-topic',
        'label' => 'Ehemaliges Thema',
        'sort_order' => 5,
        'is_active' => false,
    ]);
    attachManagedProfileOptionsFromJson($profile);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => ['de', 'la'],
            'interests' => ['music', 'former-topic'],
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->languages)->toBe(['de', 'la'])
        ->and($profile->interests)->toBe(['music', 'former-topic']);
});

test('arbitrary languages and interests are rejected', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('neareon-profile.edit'))
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'languages' => ['de', 'tlh'],
            'interests' => ['music', 'unknown'],
        ]))
        ->assertRedirect(route('neareon-profile.edit'))
        ->assertSessionHasErrors(['languages.1', 'interests.1']);

    expect($profile->refresh()->languages)->not->toContain('tlh')
        ->and($profile->interests)->not->toContain('unknown');
});

test('users can update visibility fields', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => ProfileVisibility::Private->value,
            'interests_visibility' => ProfileVisibility::Mutuals->value,
            'languages_visibility' => ProfileVisibility::Private->value,
            'region_visibility' => ProfileVisibility::Public->value,
            'social_counts_visibility' => ProfileVisibility::Mutuals->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Private)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Mutuals)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Private)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Mutuals);
});

test('followers visibility is valid for profile field visibility settings', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'interests_visibility' => ProfileVisibility::Followers->value,
            'languages_visibility' => ProfileVisibility::Followers->value,
            'region_visibility' => ProfileVisibility::Followers->value,
            'social_counts_visibility' => ProfileVisibility::Followers->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $profile->refresh();

    expect($profile->profile_visibility)->toBe(ProfileVisibility::Public)
        ->and($profile->interests_visibility)->toBe(ProfileVisibility::Followers)
        ->and($profile->languages_visibility)->toBe(ProfileVisibility::Followers)
        ->and($profile->region_visibility)->toBe(ProfileVisibility::Followers)
        ->and($profile->social_counts_visibility)->toBe(ProfileVisibility::Followers);
});

test('followers visibility is rejected for whole profile visibility', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($user)
        ->from(route('neareon-profile.edit'))
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => ProfileVisibility::Followers->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'))
        ->assertSessionHasErrors('profile_visibility');

    expect($profile->refresh()->profile_visibility)->toBe(ProfileVisibility::Public);
});

test('saved visibility fields are returned after updating and reloading profile editing', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => ProfileVisibility::Mutuals->value,
            'interests_visibility' => ProfileVisibility::Private->value,
            'languages_visibility' => ProfileVisibility::Mutuals->value,
            'region_visibility' => ProfileVisibility::Private->value,
            'social_counts_visibility' => ProfileVisibility::Public->value,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.profile_visibility', 'mutuals')
            ->where('profile.interests_visibility', 'private')
            ->where('profile.languages_visibility', 'mutuals')
            ->where('profile.region_visibility', 'private')
            ->where('profile.social_counts_visibility', 'public'),
        );
});

test('invalid visibility values are rejected', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($user)
        ->from(route('neareon-profile.edit'))
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'profile_visibility' => 'friends',
        ]))
        ->assertRedirect(route('neareon-profile.edit'))
        ->assertSessionHasErrors('profile_visibility');

    expect($profile->refresh()->profile_visibility)->toBe(ProfileVisibility::Public);
});

test('username is not changed by profile editing', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'username' => 'stable_name',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'username' => 'changed_name',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->username)->toBe('stable_name');
});

test('birthdate is not changed by profile editing', function () {
    $user = User::factory()->create([
        'birthdate' => '2000-06-16',
    ]);
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'birthdate' => '1990-01-01',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($user->refresh()->birthdate->toDateString())->toBe('2000-06-16');
});

test('users cannot edit another users profile', function () {
    $user = User::factory()->create();
    $ownProfile = Profile::factory()->for($user)->create([
        'display_name' => 'Own Profile',
    ]);

    $otherUser = User::factory()->create();
    $otherProfile = Profile::factory()->for($otherUser)->create([
        'display_name' => 'Other Profile',
    ]);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), validProfileUpdatePayload([
            'user_id' => $otherUser->id,
            'display_name' => 'Updated Own Profile',
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($ownProfile->refresh()->display_name)->toBe('Updated Own Profile')
        ->and($otherProfile->refresh()->display_name)->toBe('Other Profile');
});
