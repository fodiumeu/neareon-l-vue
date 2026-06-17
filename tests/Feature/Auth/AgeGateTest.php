<?php

use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open age gate', function () {
    $this->get(route('age-gate.show'))
        ->assertRedirect(route('login'));
});

test('authenticated users without age gate can open age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->get(route('age-gate.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AgeGate'),
        );
});

test('users under fourteen are blocked by age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->from(route('age-gate.show'))
        ->post(route('age-gate.store'), [
            'birthdate' => now()->subYears(14)->addDay()->toDateString(),
        ])
        ->assertRedirect(route('age-gate.show'))
        ->assertSessionHasErrors([
            'birthdate' => 'NEAREON kann aktuell erst ab 14 Jahren genutzt werden.',
        ]);

    $user->refresh();

    expect($user->birthdate)->toBeNull()
        ->and($user->age_gate_passed_at)->toBeNull();
});

test('future birthdates are blocked by age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->from(route('age-gate.show'))
        ->post(route('age-gate.store'), [
            'birthdate' => now()->addDay()->toDateString(),
        ])
        ->assertRedirect(route('age-gate.show'))
        ->assertSessionHasErrors('birthdate');
});

test('users fourteen and older pass age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();
    $birthdate = now()->subYears(14)->subDay()->toDateString();

    $this->actingAs($user)
        ->post(route('age-gate.store'), [
            'birthdate' => $birthdate,
        ])
        ->assertRedirect(route('onboarding.create'));

    $user->refresh();

    expect($user->birthdate->toDateString())->toBe($birthdate)
        ->and($user->age_gate_passed_at)->not->toBeNull();
});

test('users with completed age gate are redirected away from age gate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('age-gate.show'))
        ->assertRedirect(route('onboarding.create'));
});

test('users without age gate are redirected from dashboard to age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('age-gate.show'));
});

test('users without age gate are redirected from onboarding to age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertRedirect(route('age-gate.show'));
});

test('users without age gate are redirected from discover to age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertRedirect(route('age-gate.show'));
});

test('users without age gate are redirected from profile editing to age gate', function () {
    $user = User::factory()->withoutAgeGate()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertRedirect(route('age-gate.show'));
});

test('users without age gate cannot follow profiles', function () {
    $user = User::factory()->withoutAgeGate()->create();
    Profile::factory()->for($user)->create();

    $targetProfile = Profile::factory()->create([
        'username' => 'age_gate_follow_target',
    ]);

    $this->actingAs($user)
        ->post(route('public-profile.follow', $targetProfile->username))
        ->assertRedirect(route('age-gate.show'));

    expect(Follow::query()->exists())->toBeFalse();
});

test('users with age gate but without profile are redirected to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

test('users with age gate and profile can access app areas', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('discover'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('neareon-profile.edit'))
        ->assertOk();
});
