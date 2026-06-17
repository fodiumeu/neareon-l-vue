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

    $response
        ->assertOk()
        ->assertDontSee('Vollstaendiger Name')
        ->assertDontSee('Geburtsdatum');
});

test('new users can register with only email and password', function () {
    $response = $this->post(route('register.store'), [
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('age-gate.show'));

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->name)->toBe('test')
        ->and($user->birthdate)->toBeNull()
        ->and($user->age_gate_passed_at)->toBeNull();
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
