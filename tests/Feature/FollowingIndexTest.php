<?php

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function createFollowingFor(
    User $viewer,
    string $username,
    ?string $followedAt = null,
    array $profileAttributes = [],
): User {
    $followed = User::factory()->create();
    createOnboardedProfile($followed, array_merge([
        'display_name' => str($username)->replace('_', ' ')->title()->toString(),
        'username' => $username,
        'profile_visibility' => ProfileVisibility::Public,
    ], $profileAttributes));
    $follow = Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $followed->id,
    ]);

    if ($followedAt !== null) {
        $follow->forceFill([
            'created_at' => $followedAt,
            'updated_at' => $followedAt,
        ])->save();
    }

    return $followed;
}

test('guests cannot view following', function () {
    $this->get(route('following.index'))
        ->assertRedirect(route('login'));
});

test('followed profiles are shown newest first with profile and follow data', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $older = createFollowingFor(
        $viewer,
        'older_following',
        now()->subDays(2)->toDateTimeString(),
    );
    $newer = createFollowingFor(
        $viewer,
        'newer_following',
        now()->subHour()->toDateTimeString(),
        ['profile_photo_path' => 'profile-photos/newer-following.webp'],
    );

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Following/Index')
            ->has('following.data', 2)
            ->where('following.data.0.id', $newer->id)
            ->where('following.data.0.username', 'newer_following')
            ->where(
                'following.data.0.profile_photo_url',
                '/storage/profile-photos/newer-following.webp',
            )
            ->where('following.data.0.contact_status', 'none')
            ->where('following.data.0.is_followed_by', false)
            ->where('following.data.1.id', $older->id),
        );
});

test('users the viewer does not follow are hidden', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $followed = createFollowingFor($viewer, 'visible_following');
    $notFollowed = User::factory()->create();
    createOnboardedProfile($notFollowed, [
        'username' => 'not_followed',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('following.data', 1)
            ->where('following.data.0.id', $followed->id)
            ->where('following.data.0.username', 'visible_following'),
        );
});

test('blocked users and users who block the viewer are hidden', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $visible = createFollowingFor($viewer, 'visible_following');
    $blocked = createFollowingFor($viewer, 'blocked_following');
    $blocking = createFollowingFor($viewer, 'blocking_following');
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($blocked, 'blocked')
        ->create();
    Block::factory()
        ->for($blocking, 'blocker')
        ->for($viewer, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('following.data', 1)
            ->where('following.data.0.id', $visible->id),
        );
});

test('followed profiles are paginated with twelve items per page', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    foreach (range(1, 13) as $index) {
        createFollowingFor(
            $viewer,
            sprintf('paginated_following_%02d', $index),
            now()->subMinutes($index)->toDateTimeString(),
        );
    }

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('following.data', 12)
            ->where('following.current_page', 1)
            ->where('following.last_page', 2)
            ->where('following.per_page', 12)
            ->where('following.total', 13),
        );

    $this->get(route('following.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('following.data', 1)
            ->where('following.current_page', 2)
            ->where(
                'following.data.0.username',
                'paginated_following_13',
            ),
        );
});

test('equal timestamps are sorted by followed id descending', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $timestamp = now()->subHour()->toDateTimeString();
    $first = createFollowingFor($viewer, 'first_same_time', $timestamp);
    $second = createFollowingFor($viewer, 'second_same_time', $timestamp);

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('following.data.0.id', $second->id)
            ->where('following.data.1.id', $first->id),
        );
});

test('relationship statuses use existing follows and pending requests', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = createFollowingFor($viewer, 'contact_following');
    $outgoing = createFollowingFor($viewer, 'outgoing_request_following');
    $incoming = createFollowingFor($viewer, 'incoming_request_following');
    Follow::query()->create([
        'follower_id' => $contact->id,
        'followed_id' => $viewer->id,
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
        ->get(route('following.index'))
        ->assertOk();
    $following = collect($response->inertiaProps('following.data'))
        ->keyBy('username');

    expect($following['contact_following']['contact_status'])
        ->toBe('connected')
        ->and($following['contact_following']['is_followed_by'])->toBeTrue()
        ->and($following['outgoing_request_following']['contact_status'])
        ->toBe('outgoing_request')
        ->and($following['outgoing_request_following']['is_followed_by'])
        ->toBeFalse()
        ->and($following['incoming_request_following']['contact_status'])
        ->toBe('incoming_request')
        ->and($following['incoming_request_following']['is_followed_by'])
        ->toBeFalse();
});

test('visibility rules are applied before pagination', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $public = createFollowingFor($viewer, 'public_following');
    $followersOnly = createFollowingFor($viewer, 'followers_only_following', profileAttributes: [
        'profile_visibility' => ProfileVisibility::Followers,
    ]);
    $mutualOnly = createFollowingFor($viewer, 'mutual_only_following', profileAttributes: [
        'profile_visibility' => ProfileVisibility::Mutuals,
    ]);
    Follow::query()->create([
        'follower_id' => $mutualOnly->id,
        'followed_id' => $viewer->id,
    ]);
    createFollowingFor($viewer, 'private_following', profileAttributes: [
        'profile_visibility' => ProfileVisibility::Private,
    ]);
    createFollowingFor($viewer, 'contacts_hidden_following', profileAttributes: [
        'profile_visibility' => ProfileVisibility::Contacts,
    ]);

    $this->actingAs($viewer)
        ->get(route('following.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('following.data', 3)
            ->where('following.total', 3)
            ->where('following.data.0.id', $mutualOnly->id)
            ->where('following.data.1.id', $followersOnly->id)
            ->where('following.data.2.id', $public->id),
        );
});

test('the following page shows an empty state with discover link', function () {
    $page = file_get_contents(resource_path('js/pages/Following/Index.vue'));

    expect($page)
        ->toContain('Du folgst noch niemandem.')
        ->toContain('Entdecke Mitglieder und folge interessanten')
        ->toContain('Profilen.')
        ->toContain('Mitglieder entdecken')
        ->toContain('href="/discover"');
});

test('the following page is read only and links to profiles', function () {
    $page = file_get_contents(resource_path('js/pages/Following/Index.vue'));

    expect($page)
        ->toContain('Du folgst')
        ->toContain('Kontakt')
        ->toContain('Anfrage offen')
        ->toContain('Profil ansehen')
        ->toContain('`/u/${followedProfile.username}`')
        ->toContain('← Vorherige')
        ->toContain('Nächste →')
        ->not->toContain('Entfolgen')
        ->not->toContain('<Form')
        ->not->toContain('Nachricht senden')
        ->not->toContain('Kontaktanfrage senden');
});

test('the following route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('following.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects the following page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('following.index'))
        ->assertRedirect(route('onboarding.details'));
});
