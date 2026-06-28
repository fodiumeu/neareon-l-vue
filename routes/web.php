<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\InterestOptionController;
use App\Http\Controllers\Admin\LanguageOptionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\AgeGateController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\FollowerController;
use App\Http\Controllers\FollowingController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NavigationBadgeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('age-gate', [AgeGateController::class, 'show'])->name('age-gate.show');
    Route::post('age-gate', [AgeGateController::class, 'store'])->name('age-gate.store');
});

Route::middleware(['auth', 'age.gate', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding.create');
    Route::get('onboarding/details', [OnboardingController::class, 'details'])->name('onboarding.details');
    Route::post('onboarding/details', [OnboardingController::class, 'storeDetails'])->name('onboarding.details.store');
    Route::get('onboarding/interests', [OnboardingController::class, 'interests'])->name('onboarding.interests');
    Route::post('onboarding/interests', [OnboardingController::class, 'storeInterests'])->name('onboarding.interests.store');
    Route::get('onboarding/languages', [OnboardingController::class, 'languages'])->name('onboarding.languages');
    Route::post('onboarding/languages', [OnboardingController::class, 'storeLanguages'])->name('onboarding.languages.store');
});

Route::middleware(['auth', 'age.gate', 'verified', 'onboarding.complete'])->group(function () {
    Route::get('community', CommunityController::class)
        ->name('community.index');
    Route::get('groups', [GroupController::class, 'index'])
        ->name('groups.index');
    Route::get('groups/create', [GroupController::class, 'create'])
        ->name('groups.create');
    Route::post('groups', [GroupController::class, 'store'])
        ->name('groups.store');
    Route::get('my-groups', [GroupController::class, 'mine'])
        ->name('groups.mine');
    Route::get('groups/invite/{token}', [GroupController::class, 'showInvite'])
        ->name('groups.invite.show');
    Route::post('groups/invite/{token}/join', [GroupController::class, 'joinInvite'])
        ->name('groups.invite.join');
    Route::get('groups/{group:slug}/edit', [GroupController::class, 'edit'])
        ->name('groups.edit');
    Route::get('groups/{group:slug}/members', [GroupController::class, 'members'])
        ->name('groups.members.index');
    Route::delete('groups/{group:slug}/members/{member}', [GroupController::class, 'destroyMember'])
        ->name('groups.members.destroy');
    Route::patch('groups/{group:slug}/members/{member}/role', [GroupController::class, 'updateMemberRole'])
        ->name('groups.members.role.update');
    Route::post('groups/{group:slug}/join', [GroupController::class, 'join'])
        ->name('groups.join');
    Route::delete('groups/{group:slug}/membership', [GroupController::class, 'leave'])
        ->name('groups.membership.destroy');
    Route::post('groups/{group:slug}/invite-token', [GroupController::class, 'storeInviteToken'])
        ->name('groups.invite-token.store');
    Route::patch('groups/{group:slug}/requests/{member}/accept', [GroupController::class, 'acceptRequest'])
        ->name('groups.requests.accept');
    Route::delete('groups/{group:slug}/requests/{member}/decline', [GroupController::class, 'declineRequest'])
        ->name('groups.requests.decline');
    Route::patch('groups/{group:slug}', [GroupController::class, 'update'])
        ->name('groups.update');
    Route::get('groups/{group:slug}', [GroupController::class, 'show'])
        ->name('groups.show');
    Route::get('events', [EventController::class, 'index'])
        ->name('events.index');
    Route::get('my-events', [EventController::class, 'mine'])
        ->name('events.mine');
    Route::get('events/create', [EventController::class, 'create'])
        ->name('events.create');
    Route::post('events', [EventController::class, 'store'])
        ->name('events.store');
    Route::post('events/{event:slug}/attendance', [EventController::class, 'storeAttendance'])
        ->name('events.attendance.store');
    Route::delete('events/{event:slug}/attendance', [EventController::class, 'destroyAttendance'])
        ->name('events.attendance.destroy');
    Route::patch('events/{event:slug}/attendance/{attendee}/accept', [EventController::class, 'acceptAttendanceRequest'])
        ->name('events.attendance.accept');
    Route::delete('events/{event:slug}/attendance/{attendee}/decline', [EventController::class, 'declineAttendanceRequest'])
        ->name('events.attendance.decline');
    Route::get('events/{event:slug}/edit', [EventController::class, 'edit'])
        ->name('events.edit');
    Route::patch('events/{event:slug}/cancel', [EventController::class, 'cancel'])
        ->name('events.cancel');
    Route::patch('events/{event:slug}/restore', [EventController::class, 'restore'])
        ->name('events.restore');
    Route::patch('events/{event:slug}', [EventController::class, 'update'])
        ->name('events.update');
    Route::get('events/{event:slug}', [EventController::class, 'show'])
        ->name('events.show');
    Route::get('contacts', [ContactController::class, 'index'])
        ->name('contacts.index');
    Route::get('followers', [FollowerController::class, 'index'])
        ->name('followers.index');
    Route::get('following', [FollowingController::class, 'index'])
        ->name('following.index');
    Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])
        ->name('contacts.destroy');
    Route::post('contacts/{contact}/messages', [ContactController::class, 'message'])
        ->name('contacts.messages');
    Route::get('contact-requests', [ContactRequestController::class, 'index'])
        ->name('contact-requests.index');
    Route::get('contact-requests/sent', [ContactRequestController::class, 'sent'])
        ->name('contact-requests.sent');
    Route::get('blocked-profiles', [BlockController::class, 'index'])
        ->name('blocked-profiles.index');
    Route::post('contact-requests', [ContactRequestController::class, 'store'])
        ->name('contact-requests.store');
    Route::patch('contact-requests/{contactRequest}/accept', [ContactRequestController::class, 'accept'])
        ->name('contact-requests.accept');
    Route::patch('contact-requests/{contactRequest}/decline', [ContactRequestController::class, 'decline'])
        ->name('contact-requests.decline');
    Route::get('discover', [DiscoverController::class, 'index'])->name('discover');
    Route::get('messages', [MessageController::class, 'index'])
        ->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])
        ->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])
        ->name('messages.store');
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])
        ->name('notifications.open');
    Route::get('navigation/badges', NavigationBadgeController::class)
        ->name('navigation.badges');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::get('profile', [ProfileController::class, 'me'])->name('neareon-profile.show');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('neareon-profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('neareon-profile.update');
    Route::get('u/{username}', [ProfileController::class, 'show'])->name('public-profile.show');
    Route::post('u/{username}/reports', [ReportController::class, 'store'])
        ->name('public-profile.reports.store');
    Route::post('u/{username}/follow', [FollowController::class, 'store'])->name('public-profile.follow');
    Route::delete('u/{username}/follow', [FollowController::class, 'destroy'])->name('public-profile.unfollow');
    Route::post('u/{username}/block', [BlockController::class, 'store'])->name('public-profile.block');
    Route::delete('u/{username}/block', [BlockController::class, 'destroy'])->name('public-profile.unblock');
    Route::get('dashboard', function (Request $request) {
        return Inertia::render('Dashboard');
    })->name('dashboard');
    Route::get('admin', [AdminController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin');
    Route::get('admin/system', [AdminController::class, 'system'])
        ->middleware('role:admin')
        ->name('admin.system');
    Route::get('admin/project', [AdminController::class, 'project'])
        ->middleware('role:admin')
        ->name('admin.project');
    Route::get('admin/reports', [AdminReportController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.reports');
    Route::patch('admin/reports/{report}/status', [AdminReportController::class, 'toggleStatus'])
        ->middleware('role:admin')
        ->name('admin.reports.status');
    Route::get('admin/options', [AdminController::class, 'options'])
        ->middleware('role:admin')
        ->name('admin.options');
    Route::get('admin/options/languages', [LanguageOptionController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.options.languages');
    Route::post('admin/options/languages', [LanguageOptionController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.options.languages.store');
    Route::patch('admin/options/languages/{languageOption}', [LanguageOptionController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.options.languages.update');
    Route::patch('admin/options/languages/{languageOption}/status', [LanguageOptionController::class, 'toggleStatus'])
        ->middleware('role:admin')
        ->name('admin.options.languages.status');
    Route::get('admin/options/interests', [InterestOptionController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.options.interests');
    Route::post('admin/options/interests', [InterestOptionController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.options.interests.store');
    Route::patch('admin/options/interests/{interestOption}', [InterestOptionController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.options.interests.update');
    Route::patch('admin/options/interests/{interestOption}/status', [InterestOptionController::class, 'toggleStatus'])
        ->middleware('role:admin')
        ->name('admin.options.interests.status');
    Route::get('admin/users/{user}', [AdminController::class, 'show'])
        ->middleware('role:admin')
        ->name('admin.users.show');
    Route::patch('admin/users/{user}/role', [AdminController::class, 'updateRole'])
        ->middleware('role:admin')
        ->name('admin.users.role.update');
});

require __DIR__.'/settings.php';
