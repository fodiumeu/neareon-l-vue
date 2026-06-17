<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('users default to the member role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Member);
});

test('users can be assigned the admin role via the factory', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin);
    expect($user->isAdmin())->toBeTrue();
    expect($user->canAccessAdmin())->toBeTrue();
    expect($user->hasRole(UserRole::Admin))->toBeTrue();
});

test('users can be assigned the moderator role via the factory', function () {
    $user = User::factory()->moderator()->create();

    expect($user->role)->toBe(UserRole::Moderator);
    expect($user->isModerator())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
    expect($user->isOwner())->toBeFalse();
    expect($user->canAccessAdmin())->toBeFalse();
});

test('users can be assigned the owner role via the factory', function () {
    $user = User::factory()->owner()->create();

    expect($user->role)->toBe(UserRole::Owner);
    expect($user->isOwner())->toBeTrue();
    expect($user->isAdmin())->toBeFalse();
    expect($user->canAccessAdmin())->toBeTrue();
    expect($user->hasAtLeastRole(UserRole::Admin))->toBeTrue();
});

test('role levels follow the expected hierarchy', function () {
    expect(UserRole::Member->level())->toBeLessThan(UserRole::Moderator->level())
        ->and(UserRole::Moderator->level())->toBeLessThan(UserRole::Admin->level())
        ->and(UserRole::Admin->level())->toBeLessThan(UserRole::Owner->level());
});

test('role middleware allows higher roles for moderator routes', function () {
    Route::get('/test-role-moderator', fn () => 'ok')->middleware(['auth', 'role:moderator']);

    $this->actingAs(User::factory()->create())
        ->get('/test-role-moderator')
        ->assertForbidden();

    $this->actingAs(User::factory()->moderator()->create())
        ->get('/test-role-moderator')
        ->assertOk();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/test-role-moderator')
        ->assertOk();

    $this->actingAs(User::factory()->owner()->create())
        ->get('/test-role-moderator')
        ->assertOk();
});

test('role middleware allows owners for admin routes', function () {
    Route::get('/test-role-admin', fn () => 'ok')->middleware(['auth', 'role:admin']);

    $this->actingAs(User::factory()->moderator()->create())
        ->get('/test-role-admin')
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/test-role-admin')
        ->assertOk();

    $this->actingAs(User::factory()->owner()->create())
        ->get('/test-role-admin')
        ->assertOk();
});
