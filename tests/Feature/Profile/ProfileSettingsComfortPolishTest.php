<?php

test('profile editing explains visible profile details and save feedback', function () {
    $page = file_get_contents(resource_path('js/pages/Profile/Edit.vue'));
    $normalizedPage = preg_replace('/\s+/', ' ', $page);

    expect($normalizedPage)
        ->toContain('Passe deinen öffentlichen Auftritt')
        ->toContain('Dein Profilbild erscheint auf deinem Profil')
        ->toContain('in Discover, Kontakten und Nachrichten')
        ->toContain('Dieser Name ist in der Community sichtbar.')
        ->toContain('Account-Name in den Einstellungen bleibt davon getrennt')
        ->toContain('Erzähle kurz, wer du bist oder wonach du suchst.')
        ->toContain('Maximal 280 Zeichen.')
        ->toContain('Zeilenumbrüche und Emojis bleiben auf deinem Profil erhalten')
        ->toContain('Die Region hilft anderen Mitgliedern')
        ->toContain('Maximal 20 Sprachen.')
        ->toContain('Maximal 20 Interessen.')
        ->toContain('Wird gespeichert...')
        ->toContain('Änderungen speichern');
});

test('account settings distinguish account data from the community profile', function () {
    $page = file_get_contents(resource_path('js/pages/settings/Profile.vue'));
    $normalizedPage = preg_replace('/\s+/', ' ', $page);

    expect($normalizedPage)
        ->toContain('Verwalte die Zugangsdaten deines Accounts.')
        ->toContain('Dein Community-Profil bearbeitest du separat.')
        ->toContain('Account-Name und E-Mail gehören zur Anmeldung.')
        ->toContain('sichtbaren NEAREON-Anzeigenamen')
        ->toContain('NEAREON-Profil bearbeiten')
        ->toContain('Der Account-Name wird für deine Anmeldung')
        ->toContain('Auf deinem NEAREON-Profil erscheint dein Anzeigename.')
        ->toContain('Account-Sicherheit')
        ->toContain('Wird gespeichert...')
        ->toContain('Speichern');
});

test('account deletion warning names the affected profile and data', function () {
    $component = file_get_contents(resource_path('js/components/DeleteUser.vue'));
    $normalizedComponent = preg_replace('/\s+/', ' ', $component);

    expect($normalizedComponent)
        ->toContain('Diese Aktion betrifft auch dein NEAREON-Profil.')
        ->toContain('Dein Profil, deine Account-Daten und zugehörige Inhalte')
        ->toContain('dauerhaft entfernt.');
});
