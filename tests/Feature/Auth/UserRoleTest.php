<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
