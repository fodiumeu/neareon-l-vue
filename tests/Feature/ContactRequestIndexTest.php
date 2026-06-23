<?php

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot view contact requests', function () {
    $this->get(route('contact-requests.index'))
        ->assertRedirect(route('login'));
});

test('users only see their own received pending contact requests', function () {
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $otherReceiver = User::factory()->create();
    $visibleSender = User::factory()->create();
    Profile::factory()->for($visibleSender)->create([
        'username' => 'visible_sender',
        'display_name' => 'Visible Sender',
    ]);
    $otherSender = User::factory()->create();

    $visible = ContactRequest::factory()
        ->for($visibleSender, 'sender')
        ->for($receiver, 'receiver')
        ->create(['message' => 'Sichtbare Anfrage']);
    ContactRequest::factory()
        ->for($otherSender, 'sender')
        ->for($otherReceiver, 'receiver')
        ->create(['message' => 'Fremde Anfrage']);

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContactRequests/Index')
            ->has('contactRequests', 1)
            ->where('contactRequests.0.id', $visible->id)
            ->where('contactRequests.0.message', 'Sichtbare Anfrage')
            ->where('contactRequests.0.sender.display_name', 'Visible Sender')
            ->where('contactRequests.0.sender.username', 'visible_sender'),
        );
});

test('accepted declined and closed contact requests are not visible', function () {
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);

    foreach ([
        ContactRequestStatus::Accepted,
        ContactRequestStatus::Declined,
        ContactRequestStatus::Closed,
    ] as $status) {
        ContactRequest::factory()
            ->for(User::factory(), 'sender')
            ->for($receiver, 'receiver')
            ->create([
                'status' => $status,
                'responded_at' => now(),
            ]);
    }

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ContactRequests/Index')
            ->has('contactRequests', 0),
        );
});

test('pending contact requests are sorted newest first', function () {
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $olderSender = User::factory()->create();
    Profile::factory()->for($olderSender)->create([
        'username' => 'older_sender',
        'display_name' => 'Older Sender',
    ]);
    $newerSender = User::factory()->create();
    Profile::factory()->for($newerSender)->create([
        'username' => 'newer_sender',
        'display_name' => 'Newer Sender',
    ]);

    ContactRequest::factory()
        ->for($olderSender, 'sender')
        ->for($receiver, 'receiver')
        ->create(['created_at' => now()->subHour()]);
    ContactRequest::factory()
        ->for($newerSender, 'sender')
        ->for($receiver, 'receiver')
        ->create(['created_at' => now()]);

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contactRequests', 2)
            ->where('contactRequests.0.sender.username', 'newer_sender')
            ->where('contactRequests.1.sender.username', 'older_sender'),
        );
});

test('received contact requests expose privacy aware commonalities', function () {
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $sender = User::factory()->create();
    createOnboardedProfile($sender, [
        'display_name' => 'Common Sender',
        'username' => 'common_sender',
    ]);
    ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();

    $this->actingAs($receiver)
        ->get(route('contact-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRequests.0.common_languages', ['Deutsch'])
            ->where('contactRequests.0.common_interests', ['Community']),
        );
});

test('received contact request page uses the shared polished card ux', function () {
    $page = file_get_contents(
        resource_path('js/pages/ContactRequests/Index.vue'),
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
        ->toContain('acceptContactRequestAction')
        ->toContain('rejectContactRequestAction')
        ->toContain('Profil ansehen');
});

test('age gate middleware protects the contact request index', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->get(route('contact-requests.index'))
        ->assertRedirect(route('age-gate.show'));
});

test('the contact request index uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('contact-requests.index')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('onboarding middleware protects the contact request index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contact-requests.index'))
        ->assertRedirect(route('onboarding.details'));
});
