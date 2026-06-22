<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view sent contact requests', function () {
    $this->get(route('contact-requests.sent'))
        ->assertRedirect(route('login'));
});

test('users only see their own sent contact requests with receiver data', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    Profile::factory()->for($receiver)->create([
        'display_name' => 'Visible Receiver',
        'username' => 'visible_receiver',
    ]);
    $otherSender = User::factory()->create();
    $visible = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create(['message' => 'Sichtbare Nachricht']);
    ContactRequest::factory()
        ->for($otherSender, 'sender')
        ->for(User::factory(), 'receiver')
        ->create();

    $this->actingAs($sender)
        ->get(route('contact-requests.sent'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContactRequests/Sent')
            ->has('contactRequests', 1)
            ->where('contactRequests.0.id', $visible->id)
            ->where('contactRequests.0.message', 'Sichtbare Nachricht')
            ->where('contactRequests.0.receiver.display_name', 'Visible Receiver')
            ->where('contactRequests.0.receiver.username', 'visible_receiver'),
        );
});

test('pending accepted and declined sent requests are visible', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);

    foreach (ContactRequestStatus::cases() as $index => $status) {
        ContactRequest::factory()
            ->for($sender, 'sender')
            ->for(User::factory(), 'receiver')
            ->create([
                'status' => $status,
                'responded_at' => $status === ContactRequestStatus::Pending
                    ? null
                    : now(),
                'created_at' => now()->subMinutes($index),
            ]);
    }

    $this->actingAs($sender)
        ->get(route('contact-requests.sent'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests', 4)
            ->where('contactRequests.0.status', 'pending')
            ->where('contactRequests.1.status', 'accepted')
            ->where('contactRequests.2.status', 'declined')
            ->where('contactRequests.3.status', 'closed'),
        );
});

test('sent contact requests are sorted newest first', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $olderReceiver = User::factory()->create();
    Profile::factory()->for($olderReceiver)->create([
        'username' => 'older_receiver',
    ]);
    $newerReceiver = User::factory()->create();
    Profile::factory()->for($newerReceiver)->create([
        'username' => 'newer_receiver',
    ]);
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($olderReceiver, 'receiver')
        ->create(['created_at' => now()->subHour()]);
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($newerReceiver, 'receiver')
        ->create(['created_at' => now()]);

    $this->actingAs($sender)
        ->get(route('contact-requests.sent'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests', 2)
            ->where('contactRequests.0.receiver.username', 'newer_receiver')
            ->where('contactRequests.1.receiver.username', 'older_receiver'),
        );
});

test('sent contact requests expose privacy aware commonalities', function () {
    $sender = User::factory()->create();
    createOnboardedProfile($sender);
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver, [
        'display_name' => 'Common Receiver',
        'username' => 'common_receiver',
    ]);
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    $this->actingAs($sender)
        ->get(route('contact-requests.sent'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.0.common_languages', ['Deutsch'])
            ->where('contactRequests.0.common_interests', ['Community']),
        );
});

test('sent contact request page uses the shared polished card ux', function () {
    $page = file_get_contents(
        resource_path('js/pages/ContactRequests/Sent.vue'),
    );

    expect($page)
        ->toContain('class="size-16 shrink-0 shadow-sm"')
        ->toContain('formatContactRelativeTime(')
        ->toContain('formatContactRelativeTimeTitle(')
        ->toContain('Gemeinsame Sprachen')
        ->toContain('contactRequest.common_languages.length -')
        ->toContain('Gemeinsame Interessen')
        ->toContain('contactRequest.common_interests.length -')
        ->toContain('md:hover:border-primary/35')
        ->toContain('motion-reduce:transition-none')
        ->toContain('overflow-x-hidden')
        ->toContain('Profil ansehen')
        ->not->toContain('Anfrage zurückziehen');
});

test('the sent contact request route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('contact-requests.sent')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects sent contact requests', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contact-requests.sent'))
        ->assertRedirect(route('onboarding.details'));
});
