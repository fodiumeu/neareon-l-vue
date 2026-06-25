<?php

test('relationship empty states offer clear next steps', function () {
    $contacts = file_get_contents(resource_path('js/pages/Contacts/Index.vue'));
    $followers = file_get_contents(resource_path('js/pages/Followers/Index.vue'));
    $receivedRequests = file_get_contents(resource_path('js/pages/ContactRequests/Index.vue'));
    $sentRequests = file_get_contents(resource_path('js/pages/ContactRequests/Sent.vue'));

    expect($contacts)
        ->toContain('Ein Kontakt')
        ->toContain('entsteht, wenn ihr euch gegenseitig folgt.')
        ->toContain('href="/discover"')
        ->toContain('Mitglieder entdecken')
        ->toContain('href="/community"')
        ->toContain('Community öffnen')
        ->and($followers)
        ->toContain('Dir folgt derzeit noch niemand.')
        ->toContain('href="/discover"')
        ->toContain('Mitglieder entdecken')
        ->and($receivedRequests)
        ->toContain('Neue')
        ->toContain('Anfragen erscheinen hier')
        ->toContain('href="/community"')
        ->toContain('Community öffnen')
        ->and($sentRequests)
        ->toContain('Wenn')
        ->toContain('interessante Profile findest')
        ->toContain('href="/discover"')
        ->toContain('Mitglieder entdecken');
});

test('messages empty state points users back to contacts and community', function () {
    $messages = file_get_contents(resource_path('js/pages/Messages/Index.vue'));

    expect($messages)
        ->toContain('Öffne das Profil eines Kontakts')
        ->toContain('href="/contacts"')
        ->toContain('Kontakte öffnen')
        ->toContain('href="/community"')
        ->toContain('Community öffnen');
});
