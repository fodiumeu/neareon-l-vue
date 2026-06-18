<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;

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

test('an existing follow prevents a contact request', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    Follow::query()->create([
        'follower_id' => $sender->id,
        'followed_id' => $receiver->id,
    ]);

    sendContactRequest($this, $sender, $receiver)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'Du folgst diesem Benutzer bereits.');

    expect(ContactRequest::query()->exists())->toBeFalse();
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
