<?php

use App\Models\Group;
use App\Models\User;

test('unknown routes render the branded 404 page', function () {
    $this->get('/definitely-missing-neareon-page')
        ->assertNotFound()
        ->assertSee('NEAREON')
        ->assertSee('404')
        ->assertSee('Seite nicht gefunden')
        ->assertSee('Die angeforderte Seite konnte nicht gefunden werden.')
        ->assertSee('Zur Startseite')
        ->assertSee('Gruppen entdecken');
});

test('invalid private group invite links render the branded 404 page', function () {
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);

    $this->actingAs($viewer)
        ->get(route('groups.invite.show', 'invalid-token'))
        ->assertNotFound()
        ->assertSee('NEAREON')
        ->assertSee('404')
        ->assertSee('Seite nicht gefunden')
        ->assertSee('Möglicherweise ist der Link ungültig oder nicht mehr verfügbar.');
});

test('forbidden group management renders the branded 403 page', function () {
    $owner = User::factory()->create();
    createOnboardedProfile($owner);
    $viewer = User::factory()->create();
    createOnboardedProfile($viewer);
    $group = Group::factory()->for($owner, 'owner')->create([
        'slug' => 'forbidden-error-page-group',
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.edit', $group->slug))
        ->assertForbidden()
        ->assertSee('NEAREON')
        ->assertSee('403')
        ->assertSee('Zugriff verweigert')
        ->assertSee('Du hast keine Berechtigung, diese Seite aufzurufen.');
});

test('the branded 500 page can be rendered without inertia state', function () {
    expect(view('errors.500')->render())
        ->toContain('NEAREON')
        ->toContain('500')
        ->toContain('Etwas ist schiefgelaufen')
        ->toContain('Beim Laden der Seite ist ein unerwarteter Fehler aufgetreten.');
});
