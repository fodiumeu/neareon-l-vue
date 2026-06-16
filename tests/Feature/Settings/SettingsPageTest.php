<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page for general settings', function () {
    $this->get('/settings/general')
        ->assertRedirect(route('login'));
});

test('verified users can view the general settings page', function () {
    config([
        'app.name' => 'Starter Kit App',
        'app.project.tagline' => 'Reusable Laravel, Vue and Inertia foundation',
        'app.project.dashboard_title' => 'Project workspace',
    ]);

    $user = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($user)
        ->get('/settings/general')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/General')
            ->where('app.name', 'Starter Kit App')
            ->where('auth.user.email', 'member@example.com')
            ->where('auth.user.role', 'member')
            ->where('project.tagline', 'Reusable Laravel, Vue and Inertia foundation')
            ->where('project.dashboardTitle', 'Project workspace'),
        );
});
