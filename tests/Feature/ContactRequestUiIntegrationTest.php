<?php

use App\Enums\ContactRequestStatus;
use App\Enums\ProfileVisibility;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a contact request can be sent from a public profile context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'contact_request_target',
        'profile_visibility' => ProfileVisibility::Public,
    ]);

    $this->actingAs($viewer)
        ->from(route('public-profile.show', $profile->username))
        ->post(route('contact-requests.store'), [
            'receiver_id' => $owner->id,
        ])
        ->assertRedirect(route('public-profile.show', $profile->username));

    $this->assertDatabaseHas('contact_requests', [
        'sender_id' => $viewer->id,
        'receiver_id' => $owner->id,
        'status' => ContactRequestStatus::Pending->value,
    ]);

    $this->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'outgoing_request'),
        );
});

test('an incoming contact request can be accepted from a public profile context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'incoming_accept',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    $contactRequest = ContactRequest::factory()
        ->for($owner, 'sender')
        ->for($viewer, 'receiver')
        ->create();

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'incoming_request')
            ->where('profile.incoming_contact_request_id', $contactRequest->id),
        );

    $this->from(route('public-profile.show', $profile->username))
        ->patch(route('contact-requests.accept', $contactRequest))
        ->assertRedirect(route('public-profile.show', $profile->username));

    $this->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'connected'),
        );
});

test('an incoming contact request can be declined from a public profile context', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'incoming_decline',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    $contactRequest = ContactRequest::factory()
        ->for($owner, 'sender')
        ->for($viewer, 'receiver')
        ->create();

    $this->actingAs($viewer)
        ->from(route('public-profile.show', $profile->username))
        ->patch(route('contact-requests.decline', $contactRequest))
        ->assertRedirect(route('public-profile.show', $profile->username));

    expect($contactRequest->fresh()->status)
        ->toBe(ContactRequestStatus::Declined);

    $this->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'none'),
        );
});

test('connected profiles provide the user id required by the message action', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create([
        'username' => 'connected_message_target',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $owner->id,
    ]);
    Follow::query()->create([
        'follower_id' => $owner->id,
        'followed_id' => $viewer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'connected')
            ->where('profile.contact_user_id', $owner->id),
        );
});

test('own profiles do not receive contact action metadata', function () {
    $viewer = User::factory()->create();
    $profile = createOnboardedProfile($viewer, [
        'username' => 'own_contact_actions',
    ]);

    $this->actingAs($viewer)
        ->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.isOwnProfile', true)
            ->missing('profile.contact_user_id')
            ->missing('profile.incoming_contact_request_id'),
        );
});

test('discover provides incoming request action metadata', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $owner = User::factory()->create();
    Profile::factory()->for($owner)->create([
        'username' => 'discover_incoming_action',
        'profile_visibility' => ProfileVisibility::Public,
    ]);
    $contactRequest = ContactRequest::factory()
        ->for($owner, 'sender')
        ->for($viewer, 'receiver')
        ->create();

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.contact_user_id', $owner->id)
            ->where('profiles.data.0.incoming_contact_request_id', $contactRequest->id),
        );
});

test('contact action UI uses existing inertia endpoints for every status', function () {
    $actions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );
    $profile = file_get_contents(resource_path('js/pages/Profile/Show.vue'));
    $discover = file_get_contents(resource_path('js/pages/Discover.vue'));

    expect($actions)
        ->toContain('`/u/${username}/follow`')
        ->toContain("isFollowing ? 'delete' : 'post'")
        ->toContain("isFollowing ? 'Entfolgen' : 'Folgen'")
        ->toContain("status === 'none'")
        ->toContain('action="/contact-requests"')
        ->toContain('Kontaktanfrage senden')
        ->toContain("status === 'outgoing_request'")
        ->toContain('Kontaktanfrage gesendet')
        ->toContain("status === 'incoming_request'")
        ->toContain('/accept')
        ->toContain('/decline')
        ->toContain('Annehmen')
        ->toContain('Ablehnen')
        ->toContain("status === 'connected'")
        ->toContain('`/contacts/${userId}/messages`')
        ->toContain('method="post"')
        ->not->toContain('/messages?with=')
        ->toContain('Nachricht senden')
        ->toContain(':disabled="processing"')
        ->toContain('<Spinner v-if="processing"')
        ->and($profile)->toContain('<ContactActions')
        ->and($discover)->toContain('<ContactActions');
});

test('follow and contact actions are combined according to contact status', function () {
    $actions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );

    expect($actions)
        ->toContain("status === 'incoming_request' && contactRequestId")
        ->toContain("status === 'connected'")
        ->toContain("status === 'none'")
        ->toContain("status === 'outgoing_request'")
        ->toContain('Nachricht senden')
        ->toContain('Kontaktanfrage senden')
        ->toContain('Kontaktanfrage gesendet')
        ->toContain('Annehmen')
        ->toContain('Ablehnen')
        ->toContain('Entfolgen')
        ->toContain('Folgen');
});
