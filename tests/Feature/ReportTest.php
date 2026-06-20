<?php

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Http\Middleware\EnsureOnboardingIsComplete;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutMiddleware(EnsureOnboardingIsComplete::class);
});

test('a user can report another user multiple times', function () {
    $reporter = User::factory()->create();
    Profile::factory()->for($reporter)->create();
    $reported = User::factory()->create();
    $profile = Profile::factory()->for($reported)->create();

    foreach (['Erste Meldung', 'Zweite Meldung'] as $description) {
        $this->actingAs($reporter)
            ->post(route('public-profile.reports.store', $profile->username), [
                'reason' => ReportReason::Spam->value,
                'description' => $description,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Meldung wurde gesendet.');
    }

    expect(Report::query()->count())->toBe(2)
        ->and(Report::query()->firstOrFail()->status)->toBe(ReportStatus::Open);
});

test('a user cannot report their own profile', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('public-profile.reports.store', $profile->username), [
            'reason' => ReportReason::Spam->value,
        ])
        ->assertForbidden();

    expect(Report::query()->count())->toBe(0);
});

test('a report requires a valid reason and limits the description', function () {
    $reporter = User::factory()->create();
    Profile::factory()->for($reporter)->create();
    $profile = Profile::factory()->create();

    $this->actingAs($reporter)
        ->post(route('public-profile.reports.store', $profile->username), [
            'description' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors(['reason', 'description']);
});

test('the own profile page does not render the report action', function () {
    $profilePage = file_get_contents(resource_path('js/pages/Profile/Show.vue'));
    $moreActions = file_get_contents(
        resource_path('js/components/ProfileMoreActions.vue'),
    );

    expect($profilePage)
        ->toContain('v-if="!props.profile.isOwnProfile"')
        ->toContain('<ProfileMoreActions')
        ->and($moreActions)
        ->toContain('<ReportDialog');
});

test('admins can view reports', function () {
    $admin = User::factory()->admin()->create();
    Profile::factory()->for($admin)->create();
    $reporter = User::factory()->create(['name' => 'Melder']);
    $reported = User::factory()->create(['name' => 'Gemeldeter Benutzer']);
    $report = Report::query()->create([
        'reporter_user_id' => $reporter->id,
        'reported_user_id' => $reported->id,
        'reason' => ReportReason::Harassment,
        'description' => 'Testbeschreibung',
        'status' => ReportStatus::Open,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Reports/Index')
            ->has('reports', 1)
            ->where('reports.0.id', $report->id)
            ->where('reports.0.reporter.name', 'Melder')
            ->where('reports.0.reported_user.name', 'Gemeldeter Benutzer')
            ->where('reports.0.reason_label', 'Belästigung')
            ->where('reports.0.description', 'Testbeschreibung')
            ->where('reports.0.status', 'open'),
        );
});

test('non-admin users cannot view or update reports', function () {
    $member = User::factory()->create();
    Profile::factory()->for($member)->create();
    $report = Report::query()->create([
        'reporter_user_id' => User::factory()->create()->id,
        'reported_user_id' => User::factory()->create()->id,
        'reason' => ReportReason::Other,
        'status' => ReportStatus::Open,
    ]);

    $this->actingAs($member)
        ->get(route('admin.reports'))
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('admin.reports.status', $report))
        ->assertForbidden();
});

test('admins can close and reopen reports', function () {
    $admin = User::factory()->admin()->create();
    Profile::factory()->for($admin)->create();
    $report = Report::query()->create([
        'reporter_user_id' => User::factory()->create()->id,
        'reported_user_id' => User::factory()->create()->id,
        'reason' => ReportReason::Fraud,
        'status' => ReportStatus::Open,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.reports.status', $report))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Die Meldung wurde geschlossen.');

    expect($report->refresh()->status)->toBe(ReportStatus::Closed);

    $this->actingAs($admin)
        ->patch(route('admin.reports.status', $report))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Die Meldung wurde wieder geöffnet.');

    expect($report->refresh()->status)->toBe(ReportStatus::Open);
});
