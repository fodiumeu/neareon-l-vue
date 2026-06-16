<?php

use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('welcome page receives the shared project tagline', function () {
    config([
        'app.project.tagline' => 'Project-ready starter foundation',
        'app.project.welcome_title' => 'Project Welcome',
        'app.project.welcome_description' => 'Project-specific welcome copy.',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('project.tagline', 'Project-ready starter foundation'),
        );
});

test('welcome page receives the shared welcome copy', function () {
    config([
        'app.project.welcome_title' => 'Project Welcome',
        'app.project.welcome_description' => 'Project-specific welcome copy.',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('project.welcomeTitle', 'Project Welcome')
            ->where('project.welcomeDescription', 'Project-specific welcome copy.'),
        );
});

test('dashboard page receives the shared admin visibility flag', function () {
    config([
        'app.project.admin_label' => 'Platform',
        'app.project.show_admin_area' => false,
        'app.project.dashboard_title' => 'Project workspace',
        'app.project.dashboard_description' => 'Signed-in entry point for the current project.',
    ]);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.adminLabel', 'Platform')
            ->where('project.showAdminArea', false)
            ->where('project.dashboardTitle', 'Project workspace')
            ->where('project.dashboardDescription', 'Signed-in entry point for the current project.'),
        );
});

test('settings pages receive the shared appearance visibility flag', function () {
    config([
        'app.project.show_appearance_settings' => false,
    ]);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('project.showAppearanceSettings', false),
        );
});

test('flash messages are shared with inertia when present in the session', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->withSession([
            'success' => 'Profile saved successfully.',
            'error' => 'Something went wrong.',
        ])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('flash.success', 'Profile saved successfully.')
            ->where('flash.error', 'Something went wrong.'),
        );
});
