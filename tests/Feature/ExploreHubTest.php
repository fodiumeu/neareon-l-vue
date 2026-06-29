<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the explore hub', function () {
    $this->get(route('explore.index'))
        ->assertRedirect(route('login'));
});

test('onboarded users can open the explore hub', function () {
    $user = User::factory()->create();
    createOnboardedProfile($user);

    $this->actingAs($user)
        ->get(route('explore.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Explore/Index'),
        );
});

test('explore hub links to members groups and events discovery', function () {
    $page = file_get_contents(resource_path('js/pages/Explore/Index.vue'));

    expect($page)
        ->toContain('Mitglieder entdecken')
        ->toContain('Finde Mitglieder aus deiner Region und mit passenden Interessen.')
        ->toContain("href: '/discover'")
        ->toContain('Gruppen entdecken')
        ->toContain('Entdecke regionale und thematische Gruppen.')
        ->toContain("href: '/groups'")
        ->toContain('Events entdecken')
        ->toContain('Finde Events und Aktivitäten aus deiner Community.')
        ->toContain("href: '/events'");
});

test('explore hub points to existing discovery routes', function () {
    expect(Route::has('explore.index'))->toBeTrue()
        ->and(Route::has('discover'))->toBeTrue()
        ->and(Route::has('groups.index'))->toBeTrue()
        ->and(Route::has('events.index'))->toBeTrue();
});
