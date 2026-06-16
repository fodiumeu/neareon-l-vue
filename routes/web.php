<?php

use App\Http\Controllers\Admin\AdminController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
    Route::get('discover', [DiscoverController::class, 'index'])->name('discover');
    Route::get('profile/edit', [ProfileController::class, 'edit'])->name('neareon-profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('neareon-profile.update');
    Route::get('u/{username}', [ProfileController::class, 'show'])->name('public-profile.show');
    Route::post('u/{username}/follow', [FollowController::class, 'store'])->name('public-profile.follow');
    Route::delete('u/{username}/follow', [FollowController::class, 'destroy'])->name('public-profile.unfollow');
    Route::get('dashboard', function (Request $request) {
        if (! $request->user()->profile()->exists()) {
            return to_route('onboarding.create');
        }

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
