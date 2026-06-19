<?php

use App\Enums\ContactRequestStatus;
use App\Models\Block;
use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function createBlockTestFollow(User $follower, User $followed): Follow
{
    return Follow::query()->create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
    ]);
}

test('a user can block and unblock another user', function () {
    $blocker = User::factory()->create();
    createOnboardedProfile($blocker);
    $blocked = User::factory()->create();
    $profile = Profile::factory()->for($blocked)->create([
        'username' => 'blocked_user',
    ]);

    $this->actingAs($blocker)
        ->from(route('public-profile.show', $profile->username))
        ->post(route('public-profile.block', $profile->username))
        ->assertRedirect(route('public-profile.show', $profile->username))
        ->assertSessionHas('success', 'Benutzer wurde blockiert.');

    expect($blocker->hasBlocked($blocked))->toBeTrue();

    $this->get(route('public-profile.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.interaction_blocked', true)
            ->where('profile.is_blocked_by_viewer', true)
            ->where('profile.contact_status', 'none'),
        );

    $this->from(route('public-profile.show', $profile->username))
        ->delete(route('public-profile.unblock', $profile->username))
        ->assertRedirect(route('public-profile.show', $profile->username))
        ->assertSessionHas('success', 'Blockierung wurde aufgehoben.');

    expect($blocker->hasBlocked($blocked))->toBeFalse();
});

test('blocking removes follows connection and pending contact requests', function () {
    $blocker = User::factory()->create();
    createOnboardedProfile($blocker);
    $blocked = User::factory()->create();
    $profile = Profile::factory()->for($blocked)->create();
    createBlockTestFollow($blocker, $blocked);
    createBlockTestFollow($blocked, $blocker);
    ContactRequest::factory()
        ->for($blocked, 'sender')
        ->for($blocker, 'receiver')
        ->create();

    $this->actingAs($blocker)
        ->post(route('public-profile.block', $profile->username))
        ->assertRedirect();

    expect(Follow::query()->exists())->toBeFalse()
        ->and(ContactRequest::query()->exists())->toBeFalse()
        ->and($blocker->isMutualWith($blocked))->toBeFalse();
});

test('blocking closes an accepted contact request', function () {
    $blocker = User::factory()->create();
    createOnboardedProfile($blocker);
    $blocked = User::factory()->create();
    $profile = Profile::factory()->for($blocked)->create();
    createBlockTestFollow($blocker, $blocked);
    createBlockTestFollow($blocked, $blocker);
    $contactRequest = ContactRequest::factory()
        ->for($blocker, 'sender')
        ->for($blocked, 'receiver')
        ->create([
            'status' => ContactRequestStatus::Accepted,
            'responded_at' => now()->subDay(),
        ]);

    $this->actingAs($blocker)
        ->post(route('public-profile.block', $profile->username))
        ->assertRedirect();

    expect($contactRequest->refresh()->status)
        ->toBe(ContactRequestStatus::Closed);
});

test('blocking preserves conversation participants and message history', function () {
    $blocker = User::factory()->create();
    createOnboardedProfile($blocker);
    $blocked = User::factory()->create();
    $profile = Profile::factory()->for($blocked)->create();
    createBlockTestFollow($blocker, $blocked);
    createBlockTestFollow($blocked, $blocker);
    $conversation = Conversation::factory()->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($blocker)
        ->create();
    ConversationParticipant::factory()
        ->for($conversation)
        ->for($blocked)
        ->create();
    $message = Message::factory()
        ->for($conversation)
        ->for($blocked, 'sender')
        ->create([
            'body' => 'Bestehende Nachricht',
        ]);

    $this->actingAs($blocker)
        ->post(route('public-profile.block', $profile->username))
        ->assertRedirect();

    expect(Conversation::query()->whereKey($conversation->id)->exists())
        ->toBeTrue()
        ->and($conversation->participants()->count())->toBe(2)
        ->and(Message::query()->whereKey($message->id)->exists())->toBeTrue();

    $this->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.is_blocked', true)
            ->where('conversation.can_send_messages', false)
            ->where('conversation.messages.0.body', 'Bestehende Nachricht'),
        );

    $this->post(route('messages.store', $conversation), [
        'message' => 'Neue Nachricht',
    ])->assertForbidden();

    expect(Message::query()->count())->toBe(1);
});

test('contact requests are forbidden in either direction when blocked', function (
    string $senderRole,
) {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    createOnboardedProfile($blocker);
    createOnboardedProfile($blocked);
    Block::factory()
        ->for($blocker, 'blocker')
        ->for($blocked, 'blocked')
        ->create();
    $sender = $senderRole === 'blocker' ? $blocker : $blocked;
    $receiver = $senderRole === 'blocker' ? $blocked : $blocker;

    $this->actingAs($sender)
        ->post(route('contact-requests.store'), [
            'receiver_id' => $receiver->id,
        ])
        ->assertForbidden();

    expect(ContactRequest::query()->exists())->toBeFalse();
})->with(['blocker', 'blocked']);

test('a pending contact request cannot be accepted after a block exists', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    createOnboardedProfile($receiver);
    $contactRequest = ContactRequest::factory()
        ->for($sender, 'sender')
        ->for($receiver, 'receiver')
        ->create();
    Block::factory()
        ->for($receiver, 'blocker')
        ->for($sender, 'blocked')
        ->create();

    $this->actingAs($receiver)
        ->patch(route('contact-requests.accept', $contactRequest))
        ->assertForbidden();

    expect(Follow::query()->exists())->toBeFalse();
});

test('follow actions are forbidden in either direction when blocked', function (
    string $actorRole,
) {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();
    createOnboardedProfile($blocker);
    createOnboardedProfile($blocked, [
        'username' => 'blocked_follow_target',
    ]);
    $blocker->profile->update([
        'username' => 'blocker_follow_target',
    ]);
    Block::factory()
        ->for($blocker, 'blocker')
        ->for($blocked, 'blocked')
        ->create();
    $actor = $actorRole === 'blocker' ? $blocker : $blocked;
    $target = $actorRole === 'blocker' ? $blocked : $blocker;

    $this->actingAs($actor)
        ->post(route('public-profile.follow', $target->profile->username))
        ->assertForbidden();

    expect(Follow::query()->exists())->toBeFalse();
})->with(['blocker', 'blocked']);

test('discover hides blocked users in both directions', function (
    string $blockDirection,
) {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create();
    Block::factory()
        ->for(
            $blockDirection === 'viewer-blocks' ? $viewer : $otherUser,
            'blocker',
        )
        ->for(
            $blockDirection === 'viewer-blocks' ? $otherUser : $viewer,
            'blocked',
        )
        ->create();

    $this->actingAs($viewer)
        ->get(route('discover'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('profiles', 0),
        );
})->with(['viewer-blocks', 'viewer-is-blocked']);

test('block relationships enforce uniqueness and prevent self blocking', function () {
    $block = Block::factory()->create();

    expect($block->blocker)->toBeInstanceOf(User::class)
        ->and($block->blocked)->toBeInstanceOf(User::class);

    Block::query()->create([
        'blocker_id' => $block->blocker_id,
        'blocked_id' => $block->blocked_id,
    ]);
})->throws(QueryException::class);

test('self blocking is rejected by the database', function () {
    $user = User::factory()->create();

    Block::query()->create([
        'blocker_id' => $user->id,
        'blocked_id' => $user->id,
    ]);
})->throws(QueryException::class);

test('block routes use the required middleware', function (string $routeName) {
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
})->with(['public-profile.block', 'public-profile.unblock']);

test('block UI provides confirmation unblock and blocked messaging copy', function () {
    $blockActions = file_get_contents(
        resource_path('js/components/BlockActions.vue'),
    );
    $profile = file_get_contents(resource_path('js/pages/Profile/Show.vue'));
    $messages = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($blockActions)
        ->toContain('Benutzer blockieren?')
        ->toContain('keine Kontaktanfragen mehr')
        ->toContain('keine neuen Nachrichten mehr')
        ->toContain('Abbrechen')
        ->toContain('Blockierung aufheben')
        ->and($profile)->toContain('Benutzer blockiert')
        ->and($messages)->toContain(
            'Dieser Benutzer wurde blockiert. Neue Nachrichten sind nicht möglich.',
        );
});

test('the blocks migration can be rolled back and applied again', function () {
    $migration = require database_path(
        'migrations/2026_06_19_000001_create_blocks_table.php',
    );

    $migration->down();

    expect(Schema::hasTable('blocks'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumns('blocks', [
        'id',
        'blocker_id',
        'blocked_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});
