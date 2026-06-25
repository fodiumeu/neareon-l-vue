<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\QueryException;

test('group belongs to an owner and exposes membership relationships', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->for($owner, 'owner')->create();
    $membership = GroupMember::factory()
        ->for($group)
        ->for($member)
        ->create();

    expect($group->owner->is($owner))->toBeTrue()
        ->and($group->members()->first()->is($membership))->toBeTrue()
        ->and($member->groupMemberships()->first()->is($membership))->toBeTrue()
        ->and($member->groups()->first()->is($group))->toBeTrue()
        ->and($owner->ownedGroups()->first()->is($group))->toBeTrue();
});

test('group stores postal code and country code location fields', function () {
    $group = Group::factory()->create([
        'region' => 'Hamburg',
        'postal_code' => '20095',
        'country_code' => 'DE',
    ]);

    expect($group->refresh())
        ->region->toBe('Hamburg')
        ->postal_code->toBe('20095')
        ->country_code->toBe('DE');
});

test('group factory creates a simple regional location foundation', function () {
    $group = Group::factory()->create();

    expect($group->region)->not->toBeNull()
        ->and($group->postal_code)->not->toBeNull()
        ->and($group->country_code)->toBe('DE');
});

test('group membership is unique per user and group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->create();

    GroupMember::factory()
        ->for($group)
        ->for($user)
        ->create();

    expect(fn () => GroupMember::factory()
        ->for($group)
        ->for($user)
        ->create())->toThrow(QueryException::class);
});
