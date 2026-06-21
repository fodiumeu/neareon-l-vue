<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sendContactRequest($test, User $sender, User $receiver, array $overrides = [])
{
    return $test
        ->actingAs($sender)
        ->from(route('dashboard'))
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
            'message' => 'Hallo, ich würde dich gern kennenlernen.',
            ...$overrides,
        ]);
}

test('guests cannot send contact requests', function () {
    $receiver = User::factory()->create();

    $this->post(route('contact-requests.store'), [
        'receiver_id' => $receiver->id,
    ])->assertRedirect(route('login'));

    expect(ContactRequest::query()->exists())->toBeFalse();
});

test('users can send contact requests', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    $contactRequest = ContactRequest::query()->sole();

    expect($contactRequest->sender_id)->toBe($sender->id)
        ->and($contactRequest->receiver_id)->toBe($receiver->id)
        ->and($contactRequest->message)->toBe('Hallo, ich würde dich gern kennenlernen.')
        ->and($contactRequest->status)->toBe(ContactRequestStatus::Pending)
        ->and($contactRequest->responded_at)->toBeNull()
        ->and(Follow::query()->exists())->toBeFalse();
});

test('users cannot send contact requests to themselves', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    sendContactRequest($this, $user, $user)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Du kannst dir nicht selbst eine Kontaktanfrage senden.');

    expect(ContactRequest::query()->exists())->toBeFalse();
});

test('an existing one-way follow still allows a contact request', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    Follow::query()->create([
        'follower_id' => $sender->id,
        'followed_id' => $receiver->id,
    ]);

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1)
        ->and($sender->isFollowing($receiver))->toBeTrue()
        ->and($receiver->isFollowing($sender))->toBeFalse();
});

test('a mutual follow prevents a contact request', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    Follow::query()->create([
        'follower_id' => $sender->id,
        'followed_id' => $receiver->id,
    ]);
    Follow::query()->create([
        'follower_id' => $receiver->id,
        'followed_id' => $sender->id,
    ]);

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Ihr folgt euch bereits gegenseitig.');

    expect(ContactRequest::query()->exists())->toBeFalse();
});

test('a duplicate pending contact request is prevented', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Du hast diesem Benutzer bereits eine Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1);
});

test('an accepted contact request without a current connection is reactivated', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $acceptedRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now(),
        ]);

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1)
        ->and($acceptedRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Pending)
        ->and($acceptedRequest->responded_at)->toBeNull();
});

test('a declined contact request is reactivated instead of duplicated', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $declinedRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create([
            'message' => 'Alte Nachricht',
            'status' => ContactRequestStatus::Declined,
            'responded_at' => now()->subDay(),
        ]);

    sendContactRequest($this, $sender, $receiver, [
        'message' => 'Erneute Anfrage',
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    $declinedRequest->refresh();

    expect(ContactRequest::query()->count())->toBe(1)
        ->and($declinedRequest->status)->toBe(ContactRequestStatus::Pending)
        ->and($declinedRequest->message)->toBe('Erneute Anfrage')
        ->and($declinedRequest->responded_at)->toBeNull();

    $this->actingAs($sender)
        ->get(route('public-profile.show', $receiver->profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.contact_status', 'outgoing_request'),
        );

    $this->actingAs($sender)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profiles.data.0.contact_status', 'outgoing_request'),
        );

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests', 1)
            ->where('contactRequests.0.id', $declinedRequest->id),
        );

    $this->actingAs($sender)
        ->get(route('contact-requests.sent'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests', 1)
            ->where('contactRequests.0.id', $declinedRequest->id)
            ->where('contactRequests.0.status', 'pending'),
        );
});

test('a closed contact request in the opposite direction is reused for a new request', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $closedRequest = ContactRequest::factory()
        ->for($receiver, 'sender')
        ->for($sender, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Closed,
            'responded_at' => now()->subDay(),
        ]);

    sendContactRequest($this, $sender, $receiver, [
        'message' => 'Neue Richtung',
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    $closedRequest->refresh();

    expect(ContactRequest::query()->count())->toBe(1)
        ->and($closedRequest->sender_id)->toBe($sender->id)
        ->and($closedRequest->receiver_id)->toBe($receiver->id)
        ->and($closedRequest->status)->toBe(ContactRequestStatus::Pending)
        ->and($closedRequest->message)->toBe('Neue Richtung')
        ->and($closedRequest->responded_at)->toBeNull();
});

test('a pending contact request in the opposite direction is prevented', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    ContactRequest::factory()
        ->for($receiver, 'sender')
        ->for($sender, 'receiver')
        ->create();

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Dieser Benutzer hat dir bereits eine Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1);
});

test('contact request input is validated', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);

    $this->actingAs($sender)
        ->from(route('dashboard'))
        ->post(route('contact-requests.store'), [
            'receiver_id' => PHP_INT_MAX,
            'message' => str_repeat('a', 251),
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['receiver_id', 'message']);

    expect(ContactRequest::query()->exists())->toBeFalse();
});
