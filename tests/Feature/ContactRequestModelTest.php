<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\QueryException;

test('contact request factory creates pending requests between users', function () {
    $contactRequest = ContactRequest::factory()->create();

    expect($contactRequest->sender)->toBeInstanceOf(User::class)
        ->and($contactRequest->receiver)->toBeInstanceOf(User::class)
        ->and($contactRequest->sender->is($contactRequest->receiver))->toBeFalse()
        ->and($contactRequest->status)->toBe(ContactRequestStatus::Pending)
        ->and($contactRequest->responded_at)->toBeNull()
        ->and($contactRequest->message === null || mb_strlen($contactRequest->message) <= 250)
        ->toBeTrue();
});

test('contact request belongs to its sender and receiver', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    expect($contactRequest->sender->is($sender))->toBeTrue()
        ->and($contactRequest->receiver->is($receiver))->toBeTrue()
        ->and($sender->sentContactRequests->first()->is($contactRequest))->toBeTrue()
        ->and($receiver->receivedContactRequests->first()->is($contactRequest))->toBeTrue();
});

test('contact request status and responded at are cast', function () {
    $respondedAt = now()->startOfSecond();
    $contactRequest = ContactRequest::factory()->create([
        'status' => ContactRequestStatus::Accepted,
        'responded_at' => $respondedAt,
    ])->fresh();

    expect($contactRequest->status)->toBe(ContactRequestStatus::Accepted)
        ->and($contactRequest->responded_at->equalTo($respondedAt))->toBeTrue();
});

test('sender and receiver pairs are unique', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();
})->throws(QueryException::class);

test('the reverse sender and receiver pair is distinct', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    ContactRequest::factory()
        ->for($receiver, 'sender')
        ->for($sender, 'receiver')
        ->create();

    expect(ContactRequest::query()->count())->toBe(2);
});

test('sender and receiver must be different users', function () {
    $user = User::factory()->create();

    ContactRequest::factory()
        ->for($user, 'sender')
        ->for($user, 'receiver')
        ->create();
})->throws(QueryException::class);
