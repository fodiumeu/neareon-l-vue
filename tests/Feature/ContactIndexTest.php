<?php

use App\Enums\ContactRequestStatus;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\InterestOption;
use App\Models\LanguageOption;
use App\Models\Message;
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
    $incomingFollow = createFollow($contact, $viewer);
    $conversation = Conversation::factory()->create([
        'updated_at' => now(),
    ]);
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($viewer)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($contact)
        ->create();

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contacts/Index')
            ->has('contacts', 1)
            ->where('contacts.0.id', $contact->id)
            ->where('contacts.0.display_name', 'Mutual Contact')
            ->where('contacts.0.username', 'mutual_contact')
            ->where('contacts.0.status', 'connected')
            ->where('contacts.0.conversation_id', $conversation->id)
            ->where('contacts.0.connected_at', $incomingFollow->created_at->toIso8601String())
            ->where('contacts.0.last_activity_at', $conversation->updated_at->toIso8601String()),
        );
});

test('contacts expose all visible common languages and interests', function () {
    $viewer = User::factory()->create();
    $viewerProfile = createOnboardedProfile($viewer, [
        'username' => 'commonality_viewer',
    ]);
    $contact = User::factory()->create();
    $contactProfile = createOnboardedProfile($contact, [
        'display_name' => 'Common Contact',
        'username' => 'common_contact',
    ]);
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);

    $languages = collect(range(1, 4))->map(
        fn (int $position): LanguageOption => LanguageOption::query()->create([
            'code' => "contact-language-{$position}",
            'label' => "Sprache {$position}",
            'native_label' => "Sprache {$position}",
            'sort_order' => $position,
            'is_active' => true,
        ]),
    );
    $interests = collect(range(1, 5))->map(
        fn (int $position): InterestOption => InterestOption::query()->create([
            'slug' => "contact-interest-{$position}",
            'label' => "Interesse {$position}",
            'sort_order' => $position,
            'is_active' => true,
        ]),
    );

    foreach ($languages as $position => $language) {
        $viewerProfile->languageOptions()->attach($language, [
            'position' => $position + 1,
        ]);
        $contactProfile->languageOptions()->attach($language, [
            'position' => $position + 1,
        ]);
    }

    $viewerProfile->interestOptions()->attach($interests->pluck('id')->all());
    $contactProfile->interestOptions()->attach($interests->pluck('id')->all());

    $this->actingAs($viewer)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.0.common_languages', [
                'Deutsch',
                'Sprache 1',
                'Sprache 2',
                'Sprache 3',
                'Sprache 4',
            ])
            ->where('contacts.0.common_interests', [
                'Community',
                'Interesse 1',
                'Interesse 2',
                'Interesse 3',
                'Interesse 4',
                'Interesse 5',
            ]),
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

test('removing a connection deletes only the viewers follow direction', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);

    $this->actingAs($viewer)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'))
        ->assertSessionHas('success', 'Verbindung wurde entfernt.');

    expect($viewer->isFollowing($contact))->toBeFalse()
        ->and($contact->isFollowing($viewer))->toBeTrue()
        ->and($viewer->isMutualWith($contact))->toBeFalse();

    $this->get(route('contacts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts', 0),
        );
});

test('removing a connection closes its accepted contact request', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);
    $contactRequest = ContactRequest::factory()
        ->for($viewer, 'sender')
        ->for($contact, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now()->subDay(),
        ]);

    $this->actingAs($viewer)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    expect($contactRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Closed);
});

test('users cannot remove a foreign connection', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    createFollow($userA, $userB);
    createFollow($userB, $userA);

    $this->actingAs($viewer)
        ->delete(route('contacts.destroy', $userA))
        ->assertForbidden();

    expect($userA->isMutualWith($userB))->toBeTrue();
});

test('removing a connection preserves its conversation and messages', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);
    $conversation = Conversation::factory()->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($viewer)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($contact)
        ->create();
    $message = Message::factory()
        ->for($conversation)
        ->for($contact, 'sender')
        ->create();

    $this->actingAs($viewer)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    $this->post(route('messages.store', $conversation), [
        'message' => 'Nach Verbindungsende',
    ])->assertForbidden();

    $this->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.can_send_messages', false)
            ->where('conversation.messages.0.id', $message->id),
        );

    expect(Conversation::query()->whereKey($conversation->id)->exists())
        ->toBeTrue()
        ->and(ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->count())->toBe(2)
        ->and(Message::query()->whereKey($message->id)->exists())->toBeTrue()
        ->and(Message::query()->count())->toBe(1);
});

test('the message action opens or creates the contacts conversation', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);

    $this->actingAs($viewer)
        ->post(route('contacts.messages', $contact))
        ->assertRedirect();

    $conversation = Conversation::query()->sole();

    expect($conversation->participants()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$viewer->id, $contact->id]);

    $this->post(route('contacts.messages', $contact))
        ->assertRedirect(route('messages.show', $conversation));

    expect(Conversation::query()->count())->toBe(1);
});

test('the message action rejects users without a mutual contact', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);

    $this->actingAs($viewer)
        ->post(route('contacts.messages', $contact))
        ->assertForbidden();

    expect(Conversation::query()->count())->toBe(0);
});

test('the message action remains blocked when stale mutual follows exist', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $contact = User::factory()->create();
    Profile::factory()->for($contact)->create();
    createFollow($viewer, $contact);
    createFollow($contact, $viewer);
    Block::factory()
        ->for($viewer, 'blocker')
        ->for($contact, 'blocked')
        ->create();

    $this->actingAs($viewer)
        ->post(route('contacts.messages', $contact))
        ->assertForbidden();

    expect(Conversation::query()->count())->toBe(0);
});

test('the contacts page exposes profile message and removal actions', function () {
    $page = file_get_contents(resource_path('js/pages/Contacts/Index.vue'));

    expect($page)
        ->toContain('Profil ansehen')
        ->toContain('profileUrl(contact.username)')
        ->toContain('contactMessageAction')
        ->toContain('removeContactAction')
        ->toContain('Verbindung entfernen?')
        ->toContain('Bereits')
        ->toContain('ausgetauschte Nachrichten')
        ->toContain('bleiben erhalten.')
        ->toContain('Abbrechen');
});

test('the contacts page uses polished cards relative times and compact commonality badges', function () {
    $page = file_get_contents(resource_path('js/pages/Contacts/Index.vue'));
    $relativeTime = file_get_contents(
        resource_path('js/lib/contactRelativeTime.ts'),
    );

    expect($page)
        ->toContain('class="size-16 shrink-0 shadow-sm"')
        ->toContain('formatContactRelativeTime(')
        ->toContain('formatContactRelativeTimeTitle(')
        ->toContain('Gemeinsame Sprachen')
        ->toContain('contact.common_languages.slice(')
        ->toContain('contact.common_languages.length - 2')
        ->toContain('Gemeinsame Interessen')
        ->toContain('contact.common_interests.slice(')
        ->toContain('contact.common_interests.length - 3')
        ->toContain('motion-reduce:transition-none')
        ->toContain('md:hover:border-primary/35')
        ->and($relativeTime)
        ->toContain("return 'heute'")
        ->toContain("return 'gestern'")
        ->toContain("'Minute' : 'Minuten'")
        ->toContain("'Stunde' : 'Stunden'")
        ->toContain("'Tag' : 'Tagen'");
});

test('the contact removal route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('contacts.destroy')
        ->gatherMiddleware();

    expect($middleware)->toContain(
        'web',
        'auth',
        'age.gate',
        'verified',
        'onboarding.complete',
    );
});

test('the contact message route uses the required middleware', function () {
    $middleware = Route::getRoutes()
        ->getByName('contacts.messages')
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
