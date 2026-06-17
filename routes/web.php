<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AgeGateController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
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
    Route::get('discover', [DiscoverController::class, 'index'])->name('discover');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('neareon-profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('neareon-profile.update');
    Route::get('u/{username}', [ProfileController::class, 'show'])->name('public-profile.show');
    Route::post('u/{username}/follow', [FollowController::class, 'store'])->name('public-profile.follow');
    Route::delete('u/{username}/follow', [FollowController::class, 'destroy'])->name('public-profile.unfollow');
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
    Route::get('admin/users/{user}', [AdminController::class, 'show'])
        ->middleware('role:admin')
        ->name('admin.users.show');
    Route::patch('admin/users/{user}/role', [AdminController::class, 'updateRole'])
        ->middleware('role:admin')
        ->name('admin.users.role.update');
});

require __DIR__.'/settings.php';
