<?php

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\InternalNotification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Profile::created(fn (Profile $profile) => completeManagedProfile($profile));
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard provides the home greeting and empty states', function () {
    $user = User::factory()->create(['name' => 'Ada Account']);
    Profile::factory()
        ->for($user)
        ->create([
            'display_name' => 'Ada Lovelace',
            'username' => 'ada',
            'region' => 'Berlin',
        ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('home.user.name', 'Ada Lovelace')
            ->where('home.user.username', 'ada')
            ->where('home.user.region', 'Berlin')
            ->where('home.openItems', [])
            ->where('home.upcomingEvents', [])
            ->where('home.groups', []),
        );
});

test('dashboard vue contains the expected quick links and explore cta', function () {
    $dashboardVue = file_get_contents(resource_path('js/pages/Dashboard.vue'));

    expect($dashboardVue)
        ->toContain('Mitglieder entdecken')
        ->toContain('href: \'/discover?from=home\'')
        ->toContain('Gruppen entdecken')
        ->toContain('href: \'/groups?from=home\'')
        ->toContain('Events entdecken')
        ->toContain('href: \'/events?from=home\'')
        ->toContain('Meine Gruppen')
        ->toContain('href: \'/my-groups?from=home\'')
        ->toContain('Meine Events')
        ->toContain('href: \'/my-events?from=home\'')
        ->toContain('Nachrichten')
        ->toContain('href: \'/messages?from=home\'')
        ->toContain('Profil bearbeiten')
        ->toContain('href: \'/profile/edit?from=home\'')
        ->toContain('href="/explore"')
        ->toContain('Entdecken öffnen')
        ->toContain('href="/my-events?from=home"')
        ->toContain('href="/my-groups?from=home"');
});

test('dashboard summarizes open contact requests notifications and manageable requests', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $contactSender = User::factory()->create();
    Profile::factory()->for($contactSender)->create();
    ContactRequest::factory()->create([
        'sender_id' => $contactSender->id,
        'receiver_id' => $user->id,
        'status' => ContactRequestStatus::Pending,
    ]);

    $user->notify(new InternalNotification(
        InternalNotificationType::NewFollower,
        'Neue Person folgt dir',
        'Ein Mitglied folgt dir jetzt.',
        route('followers.index', absolute: false),
    ));

    $ownedGroup = Group::factory()->for($user, 'owner')->create();
    $groupRequester = User::factory()->create();
    Profile::factory()->for($groupRequester)->create();
    GroupMember::factory()->create([
        'group_id' => $ownedGroup->id,
        'user_id' => $groupRequester->id,
        'role' => GroupMember::ROLE_MEMBER,
        'status' => GroupMember::STATUS_PENDING,
        'joined_at' => null,
    ]);

    $ownedEvent = Event::factory()->for($user, 'owner')->create();
    $eventRequester = User::factory()->create();
    Profile::factory()->for($eventRequester)->create();
    EventAttendee::factory()->pending()->create([
        'event_id' => $ownedEvent->id,
        'user_id' => $eventRequester->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.openItems.0.key', 'contact_requests')
            ->where('home.openItems.0.count', 1)
            ->where('home.openItems.0.href', route('contact-requests.index', absolute: false))
            ->where('home.openItems.1.key', 'notifications')
            ->where('home.openItems.1.count', 1)
            ->where('home.openItems.1.href', route('notifications.index', absolute: false))
            ->where('home.openItems.2.key', 'group_requests')
            ->where('home.openItems.2.count', 1)
            ->where('home.openItems.2.href', route('groups.mine', absolute: false))
            ->where('home.openItems.3.key', 'event_requests')
            ->where('home.openItems.3.count', 1)
            ->where('home.openItems.3.href', route('events.mine', absolute: false)),
        );
});

test('dashboard does not include foreign group or event requests', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();
    $otherOwner = User::factory()->create();
    Profile::factory()->for($otherOwner)->create();
    $requester = User::factory()->create();
    Profile::factory()->for($requester)->create();

    $foreignGroup = Group::factory()->for($otherOwner, 'owner')->create();
    GroupMember::factory()->create([
        'group_id' => $foreignGroup->id,
        'user_id' => $requester->id,
        'role' => GroupMember::ROLE_MEMBER,
        'status' => GroupMember::STATUS_PENDING,
        'joined_at' => null,
    ]);

    $foreignEvent = Event::factory()->for($otherOwner, 'owner')->create();
    EventAttendee::factory()->pending()->create([
        'event_id' => $foreignEvent->id,
        'user_id' => $requester->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.openItems', []),
        );
});

test('dashboard lists only active upcoming owned or attended events', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();
    $otherOwner = User::factory()->create();
    Profile::factory()->for($otherOwner)->create();

    $attendedSoon = Event::factory()->for($otherOwner, 'owner')->create([
        'title' => 'Teilnahme bald',
        'starts_at' => now()->addDays(2),
        'region' => 'Hamburg',
    ]);
    EventAttendee::factory()->create([
        'event_id' => $attendedSoon->id,
        'user_id' => $user->id,
        'status' => EventAttendee::STATUS_ACTIVE,
    ]);

    $ownedLater = Event::factory()->for($user, 'owner')->create([
        'title' => 'Eigenes Event',
        'starts_at' => now()->addDays(5),
    ]);
    Event::factory()->for($user, 'owner')->create([
        'title' => 'Vergangenes Event',
        'starts_at' => now()->subDay(),
    ]);
    Event::factory()->for($user, 'owner')->create([
        'title' => 'Abgesagtes Event',
        'starts_at' => now()->addDay(),
        'status' => Event::STATUS_CANCELLED,
    ]);
    Event::factory()->for($otherOwner, 'owner')->create([
        'title' => 'Fremdes Event',
        'starts_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('home.upcomingEvents', 2)
            ->where('home.upcomingEvents.0.title', 'Teilnahme bald')
            ->where('home.upcomingEvents.0.region', 'Hamburg')
            ->where('home.upcomingEvents.0.href', route('events.show', [
                'event' => $attendedSoon->slug,
                'from' => 'home',
            ], absolute: false))
            ->where('home.upcomingEvents.1.title', 'Eigenes Event')
            ->where('home.upcomingEvents.1.href', route('events.show', [
                'event' => $ownedLater->slug,
                'from' => 'home',
            ], absolute: false)),
        );
});

test('dashboard lists only groups the user belongs to or owns', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();
    $otherUser = User::factory()->create();
    Profile::factory()->for($otherUser)->create();

    $ownedGroup = Group::factory()->for($user, 'owner')->create([
        'name' => 'Eigene Gruppe',
        'region' => 'Berlin',
    ]);
    $memberGroup = Group::factory()->for($otherUser, 'owner')->create([
        'name' => 'Mitgliedsgruppe',
        'region' => 'Hamburg',
    ]);
    GroupMember::factory()->create([
        'group_id' => $memberGroup->id,
        'user_id' => $user->id,
        'status' => GroupMember::STATUS_ACTIVE,
    ]);
    $foreignGroup = Group::factory()->for($otherUser, 'owner')->create([
        'name' => 'Fremde Gruppe',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('home.groups', 2)
            ->where('home.groups.0.name', 'Mitgliedsgruppe')
            ->where('home.groups.0.href', route('groups.show', [
                'group' => $memberGroup->slug,
                'from' => 'home',
            ], absolute: false))
            ->where('home.groups.1.name', 'Eigene Gruppe')
            ->where('home.groups.1.href', route('groups.show', [
                'group' => $ownedGroup->slug,
                'from' => 'home',
            ], absolute: false))
            ->missing('home.groups.2')
        );

    expect($foreignGroup->exists)->toBeTrue();
});

test('discover pages can link back to home from the safe home context', function (string $routeName) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route($routeName, ['from' => 'home']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('backLink.href', route('dashboard', absolute: false))
            ->where('backLink.label', 'Zurück zu Home')
            ->where('backLink.source', 'home'),
        );
})->with([
    'members discover' => 'discover',
    'groups discover' => 'groups.index',
    'events discover' => 'events.index',
]);

test('discover pages keep the explore backlink by default and with explore context', function (
    string $routeName,
    array $parameters,
    ?string $source,
) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route($routeName, $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('backLink.href', route('explore.index', absolute: false))
            ->where('backLink.label', 'Zurück zu Entdecken')
            ->where('backLink.source', $source),
        );
})->with([
    'members default' => ['discover', [], null],
    'members explore' => ['discover', ['from' => 'explore'], 'explore'],
    'groups default' => ['groups.index', [], null],
    'groups explore' => ['groups.index', ['from' => 'explore'], 'explore'],
    'events default' => ['events.index', [], null],
    'events explore' => ['events.index', ['from' => 'explore'], 'explore'],
]);

test('community overview pages can link back to home from the safe home context', function (string $routeName) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route($routeName, ['from' => 'home']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('backLink.href', route('dashboard', absolute: false))
            ->where('backLink.label', 'Zurück zu Home')
            ->where('backLink.source', 'home'),
        );
})->with([
    'my groups' => 'groups.mine',
    'my events' => 'events.mine',
]);

test('community overview pages keep the community backlink by default', function (string $routeName) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('backLink.href', route('community.index', absolute: false))
            ->where('backLink.label', 'Zurück zur Community')
            ->where('backLink.source', null),
        );
})->with([
    'my groups' => 'groups.mine',
    'my events' => 'events.mine',
]);

test('invalid backlink context does not create an external link', function (
    string $routeName,
    string $fallbackRoute,
    string $fallbackLabel,
) {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route($routeName, ['from' => 'https://evil.example']))
        ->assertOk()
        ->assertDontSee('https://evil.example')
        ->assertInertia(fn (Assert $page) => $page
            ->where('backLink.href', route($fallbackRoute, absolute: false))
            ->where('backLink.label', $fallbackLabel)
            ->where('backLink.source', null),
        );
})->with([
    'members discover' => ['discover', 'explore.index', 'Zurück zu Entdecken'],
    'groups discover' => ['groups.index', 'explore.index', 'Zurück zu Entdecken'],
    'events discover' => ['events.index', 'explore.index', 'Zurück zu Entdecken'],
    'my groups' => ['groups.mine', 'community.index', 'Zurück zur Community'],
    'my events' => ['events.mine', 'community.index', 'Zurück zur Community'],
]);

test('dashboard receives the configured dashboard copy', function () {
    config([
        'app.project.dashboard_title' => 'Project workspace',
        'app.project.dashboard_description' => 'Signed-in entry point for the current project.',
    ]);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.dashboardTitle', 'Project workspace')
            ->where('project.dashboardDescription', 'Signed-in entry point for the current project.'),
        )
        ->assertSee('Project workspace')
        ->assertSee('Signed-in entry point for the current project.');
});

test('dashboard shows the first-use hint when starter defaults are active', function () {
    $admin = User::factory()->admin()->create();
    Profile::factory()->for($admin)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', true)
            ->where('auth.user.role', 'admin'),
        );
});

test('dashboard does not flag starter defaults when central values are customized', function () {
    config([
        'app.name' => 'Custom App',
        'app.project.tagline' => 'Custom project baseline',
        'app.project.dashboard_title' => 'Project workspace',
        'app.project.admin_label' => 'Platform',
    ]);

    $admin = User::factory()->admin()->create();
    Profile::factory()->for($admin)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', false),
        );
});

test('members do not receive the admin system link in the dashboard first-use hint', function () {
    $member = User::factory()->create();
    Profile::factory()->for($member)->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', true)
            ->where('auth.user.role', 'member'),
        )
        ->assertDontSee('/admin/system');
});
