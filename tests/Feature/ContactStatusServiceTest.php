<?php

use App\Enums\ContactStatus;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\User;
use App\Services\ContactStatusService;

test('contact status is none without a connection or pending request', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();

    expect(app(ContactStatusService::class)->between($viewer, $otherUser))
        ->toBe(ContactStatus::None);
});

test('contact status detects an outgoing pending request', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($otherUser, 'receiver')
        ->create();

    expect(app(ContactStatusService::class)->between($viewer, $otherUser))
        ->toBe(ContactStatus::OutgoingRequest);
});

test('contact status detects an incoming pending request', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();
    ContactRequest::factory()
        ->for($otherUser, 'sender')
        ->for($viewer, 'receiver')
        ->create();

    expect(app(ContactStatusService::class)->between($viewer, $otherUser))
        ->toBe(ContactStatus::IncomingRequest);
});

test('contact status detects a mutual follow as connected', function () {
    $viewer = User::factory()->create();
    $otherUser = User::factory()->create();
    Follow::query()->create([
        'follower_id' => $viewer->id,
        'followed_id' => $otherUser->id,
    ]);
    Follow::query()->create([
        'follower_id' => $otherUser->id,
        'followed_id' => $viewer->id,
    ]);

    expect(app(ContactStatusService::class)->between($viewer, $otherUser))
        ->toBe(ContactStatus::Connected);
});
