<?php

use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function profileEnhancementPayload(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Emoji Member',
        'bio' => null,
        'region' => 'Berlin',
        'languages' => ['de'],
        'interests' => ['community'],
        'profile_visibility' => ProfileVisibility::Public->value,
        'interests_visibility' => ProfileVisibility::Public->value,
        'languages_visibility' => ProfileVisibility::Public->value,
        'region_visibility' => ProfileVisibility::Public->value,
        'social_counts_visibility' => ProfileVisibility::Public->value,
    ], $overrides);
}

test('profile bios preserve emojis and line breaks when saved', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user);
    $bio = "Musik 🎵 und Reisen 🚀\nSchön, dass du hier bist 😊 ❤️";

    $this->actingAs($user)
        ->patch(
            route('neareon-profile.update'),
            profileEnhancementPayload(['bio' => $bio]),
        )
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->bio)->toBe($bio);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.bio', $bio),
        );
});

test('emoji bios are delivered unchanged to public profiles and discover', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $bio = "Community 🎉\nKaffee ☕ und Musik 🎵";
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'emoji_bio_profile',
        'display_name' => 'Emoji Bio',
        'bio' => $bio,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.bio', $bio),
        );

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.bio', $bio),
        );
});

test('profile bios use escaped vue rendering and preserve whitespace', function () {
    $profile = file_get_contents(resource_path('js/pages/Profile/Show.vue'));
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));

    foreach ([$profile, $discover] as $page) {
        expect($page)
            ->toContain('whitespace-pre-wrap')
            ->not->toContain('v-html');
    }

    expect($profile)
        ->toContain('{{ props.profile.bio }}')
        ->and($discover)
        ->toContain('{{ profile.bio }}');
});

test('profile photos open in an accessible responsive lightbox', function () {
    $profile = file_get_contents(resource_path('js/pages/Profile/Show.vue'));
    $lightbox = file_get_contents(
        resource_path('js/components/ProfilePhotoLightbox.vue'),
    );

    expect($profile)
        ->toContain('<ProfilePhotoLightbox')
        ->and($lightbox)
        ->toContain('<Dialog v-if="photoUrl"')
        ->toContain('<DialogTrigger as-child>')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('Profilbild von ${alt} vergrößern')
        ->toContain('<DialogTitle class="sr-only">')
        ->toContain('<DialogDescription class="sr-only">')
        ->toContain(':src="photoUrl"')
        ->toContain('object-contain')
        ->toContain('h-[100dvh]')
        ->toContain('sm:max-w-5xl')
        ->toContain('aria-label="Profilbild schließen"')
        ->toContain('<DialogClose');
});

test('initials avatars remain non interactive and do not open the lightbox', function () {
    $lightbox = file_get_contents(
        resource_path('js/components/ProfilePhotoLightbox.vue'),
    );

    expect($lightbox)
        ->toContain('<Dialog v-if="photoUrl"')
        ->toContain('<ProfileAvatar')
        ->toContain('v-else')
        ->toContain(':fallback="fallback"');
});
