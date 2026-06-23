<?php

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function createFollowerFor(
    User $viewer,
    string $username,
    ?string $followedAt = null,
    array $profileAttributes = [],
): User {
    $follower = User::factory()->create();
    createOnboardedProfile($follower, array_merge([
        'display_name' => str($username)->replace('_', ' ')->title()->toString(),
        'username' => $username,
        'profile_visibility' => ProfileVisibility::Public,
    ], $profileAttributes));
    $follow = Follow::query()->create([
        'follower_id' => $follower->id,
        'followed_id' => $viewer->id,
    ]);

    if ($followedAt !== null) {
        $follow->forceFill([
            'created_at' => $followedAt,
            'updated_at' => $followedAt,
        ])->save();
    }

    return $follower;
}

test('guests cannot view followers', function () {
    $this->get(route('followers.index'))
        ->assertRedirect(route('login'));
});

test('followers are shown newest first with profile and follow data', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $older = createFollowerFor(
        $viewer,
        'older_follower',
        now()->subDays(2)->toDateTimeString(),
    );
    $newer = createFollowerFor(
        $viewer,
        'newer_follower',
        now()->subHour()->toDateTimeString(),
        ['profile_photo_path' => 'profile-photos/newer.webp'],
    );

    $this->actingAs($viewer)
        ->get(route('followers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Followers/Index')
            ->has('followers.data', 2)
            ->where('followers.data.0.id', $newer->id)
            ->where('followers.data.0.username', 'newer_follower')
            ->where(
                'followers.data.0.profile_photo_url',
                '/storage/profile-photos/newer.webp',
            )
            ->where('followers.data.0.contact_status', 'none')
            ->where('followers.data.0.is_following', false)
            ->where('followers.data.1.id', $older->id),
        );
});

test('blocked users and users who block the viewer are hidden', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $visible = createFollowerFor($viewer, 'visible_follower');
    $blocked = createFollowerFor($viewer, 'blocked_follower');
    $blocking = createFollowerFor($viewer, 'blocking_follower');
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($blocked, 'blocked')
        ->create();
    Block::factory()
        ->for($blocking, 'blocker')
        ->for($viewer, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->get(route('followers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followers.data', 1)
            ->where('followers.data.0.id', $visible->id),
        );
});

test('followers are paginated with twelve items per page', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    foreach (range(1, 13) as $index) {
        createFollowerFor(
            $viewer,
            sprintf('paginated_follower_%02d', $index),
            now()->subMinutes($index)->toDateTimeString(),
        );
    }

    $this->actingAs($viewer)
        ->get(route('followers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followers.data', 12)
            ->where('followers.current_page', 1)
            ->where('followers.last_page', 2)
            ->where('followers.per_page', 12)
            ->where('followers.total', 13),
        );

    $this->get(route('followers.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followers.data', 1)
            ->where('followers.current_page', 2)
            ->where(
                'followers.data.0.username',
                'paginated_follower_13',
            ),
        );
});

test('relationship statuses use existing follows and pending requests', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = createFollowerFor($viewer, 'contact_follower');
    $outgoing = createFollowerFor($viewer, 'outgoing_request_follower');
    $incoming = createFollowerFor($viewer, 'incoming_request_follower');
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $contact->id,
    ]);
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($outgoing, 'receiver')
        ->create(['status' => ContactRequestStatus::Pending]);
    ContactRequest::factory()
        ->for($incoming, 'sender')
        ->for($viewer, 'receiver')
        ->create(['status' => ContactRequestStatus::Pending]);

    $response = $this->actingAs($viewer)
        ->get(route('followers.index'))
        ->assertOk();
    $followers = collect($response->inertiaProps('followers.data'))
        ->keyBy('username');

    expect($followers['contact_follower']['contact_status'])
        ->toBe('connected')
        ->and($followers['contact_follower']['is_following'])->toBeTrue()
        ->and($followers['outgoing_request_follower']['contact_status'])
        ->toBe('outgoing_request')
        ->and($followers['outgoing_request_follower']['is_following'])
        ->toBeFalse()
        ->and($followers['incoming_request_follower']['contact_status'])
        ->toBe('incoming_request')
        ->and($followers['incoming_request_follower']['is_following'])
        ->toBeFalse();
});

test('followers with profiles hidden from the viewer are excluded before pagination', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $visible = createFollowerFor($viewer, 'public_follower');
    createFollowerFor($viewer, 'private_follower', profileAttributes: [
        'profile_visibility' => ProfileVisibility::Private,
    ]);

    $this->actingAs($viewer)
        ->get(route('followers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('followers.data', 1)
            ->where('followers.total', 1)
            ->where('followers.data.0.id', $visible->id),
        );
});

test('the followers page is read only and links to profiles', function () {
    $page = file_get_contents(resource_path('js/pages/Followers/Index.vue'));

    expect($page)
        ->toContain('Folgt dir')
        ->toContain('Kontakt')
        ->toContain('Anfrage offen')
        ->toContain('Profil ansehen')
        ->toContain('profileUrl(follower.username)')
        ->toContain('← Vorherige')
        ->toContain('Nächste →')
        ->not->toContain('Du folgst ebenfalls')
        ->not->toContain('<Form')
        ->not->toContain('Nachricht senden')
        ->not->toContain('Follower entfernen');
});

test('the followers route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('followers.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects the followers page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('followers.index'))
        ->assertRedirect(route('onboarding.details'));
});
