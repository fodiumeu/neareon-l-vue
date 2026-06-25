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
