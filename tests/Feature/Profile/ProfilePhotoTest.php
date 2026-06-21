<?php

use App\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
});

test('user can upload a profile photo', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user);

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), profilePhotoPayload([
            'profile_photo' => UploadedFile::fake()
                ->createWithContent('profile.png', validPngContents())
                ->size(500),
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $path = $profile->refresh()->profile_photo_path;

    expect($path)->toStartWith('profile-photos/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)
        ->get(route('neareon-profile.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.profile_photo_url', "/storage/{$path}"),
        );
});

test('uploading a new profile photo replaces and deletes the previous photo', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user, [
        'profile_photo_path' => 'profile-photos/old.jpg',
    ]);
    Storage::disk('public')->put($profile->profile_photo_path, 'old photo');

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), profilePhotoPayload([
            'profile_photo' => UploadedFile::fake()
                ->createWithContent('replacement.png', validPngContents())
                ->size(500),
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    $newPath = $profile->refresh()->profile_photo_path;

    expect($newPath)->not->toBe('profile-photos/old.jpg')
        ->and($newPath)->toStartWith('profile-photos/');
    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    Storage::disk('public')->assertExists($newPath);
});

test('user can remove an existing profile photo', function () {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user, [
        'profile_photo_path' => 'profile-photos/remove.jpg',
    ]);
    Storage::disk('public')->put($profile->profile_photo_path, 'photo');

    $this->actingAs($user)
        ->patch(route('neareon-profile.update'), profilePhotoPayload([
            'remove_profile_photo' => true,
        ]))
        ->assertRedirect(route('neareon-profile.edit'));

    expect($profile->refresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing('profile-photos/remove.jpg');
});

test('profile photo validates supported image type and maximum size', function (
    UploadedFile $file,
    string $message,
) {
    $user = User::factory()->create();
    $profile = createOnboardedProfile($user);

    $this->actingAs($user)
        ->from(route('neareon-profile.edit'))
        ->patch(route('neareon-profile.update'), profilePhotoPayload([
            'profile_photo' => $file,
        ]))
        ->assertRedirect(route('neareon-profile.edit'))
        ->assertSessionHasErrors([
            'profile_photo' => $message,
        ]);

    expect($profile->refresh()->profile_photo_path)->toBeNull();
})->with([
    'unsupported format' => [
        fn () => UploadedFile::fake()->create(
            'profile.gif',
            100,
            'image/gif',
        ),
        'Das Profilbild muss eine JPG-, JPEG-, PNG- oder WEBP-Datei sein.',
    ],
    'larger than five megabytes' => [
        fn () => UploadedFile::fake()
            ->createWithContent('profile.png', validPngContents())
            ->size(5121),
        'Das Profilbild darf maximal 5 MB groß sein.',
    ],
]);

test('profile responses expose the photo URL and keep a null fallback', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = createOnboardedProfile($owner, [
        'username' => 'profile_photo_response',
        'profile_photo_path' => 'profile-photos/visible.webp',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where(
                'profile.profile_photo_url',
                '/storage/profile-photos/visible.webp',
            ),
        );

    $profile->update(['profile_photo_path' => null]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.profile_photo_url', null),
        );
});

test('profile photo UI provides upload preview removal and shared fallbacks', function () {
    $edit = file_get_contents(resource_path('js/pages/Profile/Edit.vue'));
    $avatar = file_get_contents(
        resource_path('js/components/ProfileAvatar.vue'),
    );

    expect($edit)
        ->toContain('Profilbild')
        ->toContain('name="profile_photo"')
        ->toContain('accept=".jpg,.jpeg,.png,.webp')
        ->toContain('URL.createObjectURL(file)')
        ->toContain('Bild entfernen')
        ->toContain('name="remove_profile_photo"')
        ->and($avatar)
        ->toContain('<AvatarImage')
        ->toContain('v-if="props.photoUrl"')
        ->toContain('<AvatarFallback');

    foreach ([
        'Discover.vue',
        'Contacts/Index.vue',
        'ContactRequests/Index.vue',
        'ContactRequests/Sent.vue',
    ] as $page) {
        expect(file_get_contents(resource_path("js/pages/{$page}")))
            ->toContain('<ProfileAvatar');
    }

    expect(file_get_contents(resource_path('js/pages/Profile/Show.vue')))
        ->toContain('<ProfilePhotoLightbox');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profilePhotoPayload(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Photo Member',
        'bio' => 'Profil mit Bild.',
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

function validPngContents(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nGQAAAAASUVORK5CYII=',
        true,
    );
}
