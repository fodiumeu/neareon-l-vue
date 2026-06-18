<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function respondToContactRequest(
    $test,
    User $user,
    ContactRequest $contactRequest,
    string $action,
) {
    return $test
        ->actingAs($user)
        ->from(route('contact-requests.index'))
        ->patch(route("contact-requests.{$action}", $contactRequest));
}

test('the receiver can accept a pending contact request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    respondToContactRequest($this, $receiver, $contactRequest, 'accept')
        ->assertRedirect(route('contact-requests.index'))
        ->assertSessionHas('success', 'Kontaktanfrage angenommen.');

    $contactRequest->refresh();

    expect($contactRequest->status)->toBe(ContactRequestStatus::Accepted)
        ->and($contactRequest->responded_at)->not->toBeNull()
        ->and($sender->isFollowing($receiver))->toBeTrue()
        ->and($receiver->isFollowing($sender))->toBeTrue()
        ->and($sender->isMutualWith($receiver))->toBeTrue()
        ->and(Follow::query()->count())->toBe(2);
});

test('accepting preserves an existing follow and creates only the missing direction', function (
    string $existingDirection,
) {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    $existingFollow = Follow::query()->create(
        $existingDirection === 'sender-to-receiver'
            ? [
                'follower_id' => $sender->id,
                'followed_id' => $receiver->id,
            ]
            : [
                'follower_id' => $receiver->id,
                'followed_id' => $sender->id,
            ],
    );

    respondToContactRequest($this, $receiver, $contactRequest, 'accept')
        ->assertRedirect(route('contact-requests.index'));

    expect(Follow::query()->whereKey($existingFollow->id)->exists())->toBeTrue()
        ->and($sender->isFollowing($receiver))->toBeTrue()
        ->and($receiver->isFollowing($sender))->toBeTrue()
        ->and($sender->isMutualWith($receiver))->toBeTrue()
        ->and(Follow::query()->count())->toBe(2);
})->with([
    'sender already follows receiver' => 'sender-to-receiver',
    'receiver already follows sender' => 'receiver-to-sender',
]);

test('accepting does not duplicate an existing mutual follow', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    Follow::query()->create([
        'follower_id' => $sender->id,
        'followed_id' => $receiver->id,
    ]);
    Follow::query()->create([
        'follower_id' => $receiver->id,
        'followed_id' => $sender->id,
    ]);

    respondToContactRequest($this, $receiver, $contactRequest, 'accept')
        ->assertRedirect(route('contact-requests.index'));

    expect($sender->isMutualWith($receiver))->toBeTrue()
        ->and(Follow::query()
            ->where('follower_id', $sender->id)
            ->where('followed_id', $receiver->id)
            ->count())->toBe(1)
        ->and(Follow::query()
            ->where('follower_id', $receiver->id)
            ->where('followed_id', $sender->id)
            ->count())->toBe(1)
        ->and(Follow::query()->count())->toBe(2);
});

test('the receiver can decline a pending contact request', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    respondToContactRequest($this, $receiver, $contactRequest, 'decline')
        ->assertRedirect(route('contact-requests.index'))
        ->assertSessionHas('success', 'Kontaktanfrage abgelehnt.');

    $contactRequest->refresh();

    expect($contactRequest->status)->toBe(ContactRequestStatus::Declined)
        ->and($contactRequest->responded_at)->not->toBeNull()
        ->and(Follow::query()->exists())->toBeFalse();
});

test('the sender cannot respond to a contact request', function (string $action) {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    respondToContactRequest($this, $sender, $contactRequest, $action)
        ->assertForbidden();

    expect($contactRequest->refresh()->status)->toBe(ContactRequestStatus::Pending)
        ->and($contactRequest->responded_at)->toBeNull();
})->with(['accept', 'decline']);

test('another user cannot respond to a contact request', function (string $action) {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $otherUser = User::factory()->create();
    createOnboardedProfile($otherUser);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    respondToContactRequest($this, $otherUser, $contactRequest, $action)
        ->assertForbidden();

    expect($contactRequest->refresh()->status)->toBe(ContactRequestStatus::Pending)
        ->and($contactRequest->responded_at)->toBeNull();
})->with(['accept', 'decline']);

test('guests cannot respond to a contact request', function (string $action) {
    $contactRequest = ContactRequest::factory()->create();

    $this->patch(route("contact-requests.{$action}", $contactRequest))
        ->assertRedirect(route('login'));

    expect($contactRequest->refresh()->status)->toBe(ContactRequestStatus::Pending)
        ->and($contactRequest->responded_at)->toBeNull();
})->with(['accept', 'decline']);

test('processed contact requests cannot be processed again', function (
    ContactRequestStatus $currentStatus,
    string $action,
) {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $respondedAt = now()->subMinute()->startOfSecond();
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create([
            'status' => $currentStatus,
            'responded_at' => $respondedAt,
        ]);

    respondToContactRequest($this, $receiver, $contactRequest, $action)
        ->assertStatus(409);

    $contactRequest->refresh();

    expect($contactRequest->status)->toBe($currentStatus)
        ->and($contactRequest->responded_at->equalTo($respondedAt))->toBeTrue();
})->with([
    'accepted to accepted' => [ContactRequestStatus::Accepted, 'accept'],
    'accepted to declined' => [ContactRequestStatus::Accepted, 'decline'],
    'declined to accepted' => [ContactRequestStatus::Declined, 'accept'],
    'declined to declined' => [ContactRequestStatus::Declined, 'decline'],
]);

test('a processed contact request is no longer in the pending list', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    respondToContactRequest($this, $receiver, $contactRequest, 'accept')
        ->assertRedirect(route('contact-requests.index'));

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContactRequests/Index')
            ->has('contactRequests', 0),
        );
});

test('the contact request response routes use the required middleware', function (
    string $routeName,
) {
    $middleware = Route::getRoutes()
        ->getByName($routeName)
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
})->with([
    'contact-requests.accept',
    'contact-requests.decline',
]);
