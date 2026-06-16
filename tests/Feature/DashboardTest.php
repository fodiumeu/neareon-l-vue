<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard receives the configured dashboard copy', function () {
    config([
        'app.project.dashboard_title' => 'Project workspace',
        'app.project.dashboard_description' => 'Signed-in entry point for the current project.',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.dashboardTitle', 'Project workspace')
            ->where('project.dashboardDescription', 'Signed-in entry point for the current project.'),
        )
        ->assertSee('Project workspace')
        ->assertSee('Signed-in entry point for the current project.');
});

test('dashboard shows the first-use hint when starter defaults are active', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', true)
            ->where('auth.user.role', 'admin'),
        );
});

test('dashboard does not flag starter defaults when central values are customized', function () {
    config([
        'app.name' => 'Custom App',
        'app.project.tagline' => 'Custom project baseline',
        'app.project.dashboard_title' => 'Project workspace',
        'app.project.admin_label' => 'Platform',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', false),
        );
});

test('members do not receive the admin system link in the dashboard first-use hint', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('project.hasStarterDefaults', true)
            ->where('auth.user.role', 'member'),
        )
        ->assertDontSee('/admin/system');
});
