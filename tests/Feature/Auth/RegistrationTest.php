<?php

use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $birthdate = now()->subYears(14)->subDay()->toDateString();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'birthdate' => $birthdate,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->birthdate->toDateString())->toBe($birthdate);
    expect($user->age_gate_passed_at)->not->toBeNull();
});

test('new users cannot register without a birthdate', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'missing-birthdate@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertRedirect(route('register', absolute: false))
        ->assertSessionHasErrors('birthdate');

    $this->assertGuest();
    expect(User::query()->where('email', 'missing-birthdate@example.com')->exists())->toBeFalse();
});

test('new users under fourteen cannot register', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Young User',
        'email' => 'too-young@example.com',
        'birthdate' => now()->subYears(14)->addDay()->toDateString(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertRedirect(route('register', absolute: false))
        ->assertSessionHasErrors([
            'birthdate' => 'NEAREON kann aktuell erst ab 14 Jahren genutzt werden.',
        ]);

    $this->assertGuest();
    expect(User::query()->where('email', 'too-young@example.com')->exists())->toBeFalse();
});

test('birthdate and age gate timestamp are not shared with inertia auth props', function () {
    $user = User::factory()->create([
        'birthdate' => now()->subYears(20)->toDateString(),
        'age_gate_passed_at' => now(),
    ]);
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('auth.user.birthdate')
            ->missing('auth.user.age_gate_passed_at'),
        );
});
