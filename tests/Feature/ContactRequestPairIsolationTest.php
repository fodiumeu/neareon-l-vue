<?php

use App\Enums\ContactRequestStatus;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;

function contactRequestAuditUsers(int $count = 3)
{
    return User::factory()
        ->count($count)
        ->create()
        ->each(fn (User $user) => createOnboardedProfile($user));
}

function submitAuditedContactRequest($test, User $sender, User $receiver)
{
    return $test
        ->actingAs($sender)
        ->from(route('dashboard'))
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
            'message' => 'Kontaktanfrage',
        ]);
}

test('an unrelated pending request does not prevent another user from sending a request', function () {
    [$userA, $userB, $userC] = contactRequestAuditUsers();

    ContactRequest::factory()
        ->for($userA, 'sender')
        ->for($userB, 'receiver')
        ->create();

    submitAuditedContactRequest($this, $userC, $userA)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    $this->assertDatabaseHas('contact_requests', [
        'sender_id' => $userC->id,
        'receiver_id' => $userA->id,
        'status' => ContactRequestStatus::Pending->value,
    ]);
});

test('an accepted unrelated request does not prevent another user from sending a request', function () {
    [$userA, $userB, $userC] = contactRequestAuditUsers();

    $request = ContactRequest::factory()
        ->for($userA, 'sender')
        ->for($userB, 'receiver')
        ->create();

    $this->actingAs($userB)
        ->patch(route('contact-requests.accept', $request))
        ->assertSessionHas('success', 'Kontaktanfrage angenommen.');

    submitAuditedContactRequest($this, $userC, $userA)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Kontaktanfrage gesendet.');

    $this->assertDatabaseHas('contact_requests', [
        'sender_id' => $userC->id,
        'receiver_id' => $userA->id,
        'status' => ContactRequestStatus::Pending->value,
    ]);
});

test('a duplicate outgoing pending request is prevented for the same pair', function () {
    [$userA, $userB] = contactRequestAuditUsers(2);

    ContactRequest::factory()
        ->for($userA, 'sender')
        ->for($userB, 'receiver')
        ->create();

    submitAuditedContactRequest($this, $userA, $userB)
        ->assertSessionHas('error', 'Du hast diesem Benutzer bereits eine Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1);
});

test('a pending request in the opposite direction is prevented for the same pair', function () {
    [$userA, $userB] = contactRequestAuditUsers(2);

    ContactRequest::factory()
        ->for($userA, 'sender')
        ->for($userB, 'receiver')
        ->create();

    submitAuditedContactRequest($this, $userB, $userA)
        ->assertSessionHas('error', 'Dieser Benutzer hat dir bereits eine Kontaktanfrage gesendet.');

    expect(ContactRequest::query()->count())->toBe(1);
});

test('users who are already contacts cannot send another request', function () {
    [$userA, $userB] = contactRequestAuditUsers(2);

    Follow::query()->create([
        'follower_id' => $userA->id,
        'followed_id' => $userB->id,
    ]);
    Follow::query()->create([
        'follower_id' => $userB->id,
        'followed_id' => $userA->id,
    ]);

    submitAuditedContactRequest($this, $userA, $userB)
        ->assertSessionHas('error', 'Ihr folgt euch bereits gegenseitig.');

    expect(ContactRequest::query()->exists())->toBeFalse();
});

test('a block in either direction prevents a contact request', function (bool $senderBlocksReceiver) {
    [$userA, $userB] = contactRequestAuditUsers(2);

    Block::factory()->create([
        'blocker_id' => $senderBlocksReceiver ? $userA->id : $userB->id,
        'blocked_id' => $senderBlocksReceiver ? $userB->id : $userA->id,
    ]);

    submitAuditedContactRequest($this, $userA, $userB)->assertForbidden();

    expect(ContactRequest::query()->exists())->toBeFalse();
})->with([
    'sender blocks receiver' => true,
    'receiver blocks sender' => false,
]);
