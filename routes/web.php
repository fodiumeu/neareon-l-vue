<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
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
