<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings navigation hides appearance by default through the shared flag', function () {
    $user = User::factory()->create();

    expect(config('app.project.show_appearance_settings'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('project.showAppearanceSettings', false),
        );
});

test('appearance settings route remains stable when hidden from navigation', function () {
    $this->get(route('appearance.edit'))
        ->assertRedirect(route('login'));
});
