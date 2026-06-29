<?php

use App\Enums\ContactRequestStatus;
use App\Enums\InternalNotificationType;
use App\Models\ContactRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\DemoSeeder;

function runDemoSeeder(): void
{
    test()->seed(DemoSeeder::class);
}

test('demo seeder creates synthetic users and can be run repeatedly', function () {
    runDemoSeeder();
    runDemoSeeder();

    expect(User::query()->where('email', 'like', 'demo.%@neareon.test')->count())->toBe(5)
        ->and(User::query()->where('email', 'like', 'demo.%@neareon.test')->pluck('email')->all())
        ->toContain(
            'demo.fodi@neareon.test',
            'demo.mira@neareon.test',
            'demo.jonas@neareon.test',
            'demo.lea@neareon.test',
            'demo.admin@neareon.test',
        )
        ->and(User::query()->where('email', 'like', 'demo.%@neareon.test')->whereNull('email_verified_at')->count())
        ->toBe(0);
});

test('demo users have onboarded profiles with managed languages and interests', function () {
    runDemoSeeder();

    $profiles = Profile::query()
        ->whereIn('username', ['demo_fodi', 'demo_mira', 'demo_jonas', 'demo_lea', 'demo_admin'])
        ->with(['languageOptions', 'interestOptions'])
        ->get();

    expect($profiles)->toHaveCount(5);

    $profiles->each(function (Profile $profile): void {
        expect($profile->profile_photo_path)->toBeNull()
            ->and($profile->languageOptions)->not->toBeEmpty()
            ->and($profile->interestOptions)->not->toBeEmpty();
    });
});

test('demo seeder creates contact graph and pending requests', function () {
    runDemoSeeder();

    $fodi = User::query()->where('email', 'demo.fodi@neareon.test')->firstOrFail();
    $mira = User::query()->where('email', 'demo.mira@neareon.test')->firstOrFail();
    $jonas = User::query()->where('email', 'demo.jonas@neareon.test')->firstOrFail();
    $lea = User::query()->where('email', 'demo.lea@neareon.test')->firstOrFail();

    expect(Follow::query()->where('follower_id', $fodi->id)->where('followed_id', $mira->id)->exists())->toBeTrue()
        ->and(Follow::query()->where('follower_id', $mira->id)->where('followed_id', $fodi->id)->exists())->toBeTrue()
        ->and(ContactRequest::query()
            ->where('sender_id', $jonas->id)
            ->where('receiver_id', $fodi->id)
            ->where('status', ContactRequestStatus::Pending)
            ->exists())->toBeTrue()
        ->and(ContactRequest::query()
            ->where('sender_id', $fodi->id)
            ->where('receiver_id', $lea->id)
            ->where('status', ContactRequestStatus::Pending)
            ->exists())->toBeTrue();
});

test('demo seeder creates groups with active and pending memberships', function () {
    runDemoSeeder();

    $groups = Group::query()
        ->whereIn('slug', [
            'demo-hamburg-community',
            'demo-berlin-kulturtreff',
            'demo-private-tech',
        ])
        ->get()
        ->keyBy('slug');

    expect($groups)->toHaveCount(3)
        ->and($groups->get('demo-hamburg-community')->visibility)->toBe(Group::VISIBILITY_PUBLIC)
        ->and($groups->get('demo-berlin-kulturtreff')->visibility)->toBe(Group::VISIBILITY_REQUEST)
        ->and($groups->get('demo-private-tech')->visibility)->toBe(Group::VISIBILITY_PRIVATE)
        ->and(GroupMember::query()
            ->where('group_id', $groups->get('demo-hamburg-community')->id)
            ->where('role', GroupMember::ROLE_MODERATOR)
            ->where('status', GroupMember::STATUS_ACTIVE)
            ->exists())->toBeTrue()
        ->and(GroupMember::query()
            ->where('group_id', $groups->get('demo-berlin-kulturtreff')->id)
            ->where('status', GroupMember::STATUS_PENDING)
            ->exists())->toBeTrue();
});

test('demo seeder creates events with active pending and cancelled states', function () {
    runDemoSeeder();

    $events = Event::query()
        ->whereIn('slug', [
            'demo-hamburg-community-abend',
            'demo-berlin-kulturabend',
            'demo-fodi-tech-cafe',
            'demo-abgesagter-brunch',
            'demo-vergangener-spaziergang',
        ])
        ->get()
        ->keyBy('slug');

    expect($events)->toHaveCount(5)
        ->and($events->get('demo-berlin-kulturabend')->visibility)->toBe(Event::VISIBILITY_REQUEST)
        ->and($events->get('demo-abgesagter-brunch')->status)->toBe(Event::STATUS_CANCELLED)
        ->and(EventAttendee::query()
            ->where('event_id', $events->get('demo-hamburg-community-abend')->id)
            ->where('status', EventAttendee::STATUS_ACTIVE)
            ->exists())->toBeTrue()
        ->and(EventAttendee::query()
            ->where('event_id', $events->get('demo-berlin-kulturabend')->id)
            ->where('status', EventAttendee::STATUS_PENDING)
            ->exists())->toBeTrue();
});

test('demo seeder creates conversation messages and internal notifications idempotently', function () {
    runDemoSeeder();
    runDemoSeeder();

    $fodi = User::query()->where('email', 'demo.fodi@neareon.test')->firstOrFail();

    expect(Message::query()
        ->where('body', 'Hi Mira, hast du Lust auf das Community-Event?')
        ->count())->toBe(1)
        ->and($fodi->notifications()->where('data->type', InternalNotificationType::ContactRequestReceived->value)->count())
        ->toBe(1)
        ->and($fodi->notifications()->where('data->type', InternalNotificationType::EventAttendanceRequestReceived->value)->count())
        ->toBe(1)
        ->and($fodi->notifications()->where('data->type', InternalNotificationType::NewMessage->value)->count())
        ->toBe(1);
});

test('demo seeder uses only test emails and no external image urls', function () {
    runDemoSeeder();

    expect(User::query()
        ->where('email', 'like', 'demo.%')
        ->where('email', 'not like', '%@neareon.test')
        ->exists())->toBeFalse()
        ->and(Profile::query()
            ->whereNotNull('profile_photo_path')
            ->where('profile_photo_path', 'like', 'http%')
            ->exists())->toBeFalse();
});

test('demo seeder is guarded against production', function () {
    $originalEnvironment = app()->environment();

    try {
        app()->instance('env', 'production');

        expect(fn () => app(DemoSeeder::class)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->instance('env', $originalEnvironment);
    }
});

test('demo seeder is not wired into the default database seeder', function () {
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('DemoSeeder::class');
});

test('demo seeder documentation contains command and account hints', function () {
    $docs = file_get_contents(base_path('docs/demo-seeder.md'));

    expect($docs)
        ->toContain('php artisan db:seed --class=DemoSeeder')
        ->toContain('demo.fodi@neareon.test')
        ->toContain('neareon-demo');
});
