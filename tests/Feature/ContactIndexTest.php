<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function createFollow(User $follower, User $followed): Follow
{
    return Follow::query()->create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
    ]);
}

test('guests cannot view contacts', function () {
    $this->get(route('contacts.index'))
        ->assertRedirect(route('login'));
});

test('mutual follows appear in the contact list', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create([
        'display_name' => 'Mutual Contact',
        'username' => 'mutual_contact',
    ]);
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contacts/Index')
            ->has('contacts', 1)
            ->where('contacts.0.display_name', 'Mutual Contact')
            ->where('contacts.0.username', 'mutual_contact'),
        );
});

test('one-way follows do not appear in the contact list', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $followedUser = User::factory()->create();
    Profile::factory()->for($followedUser)->create();
    createFollow($viewer, $followedUser);

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 0),
        );
});

test('pending contact requests do not appear in the contact list', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create();
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($otherUser, 'receiver')
        ->create();

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 0),
        );
});

test('accepted requests without mutual follows do not appear in the contact list', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create();
    ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($otherUser, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now(),
        ]);

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 0),
        );
});

test('the contacts route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('contacts.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects the contact list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertRedirect(route('onboarding.details'));
});
