<?php

test('relationship action configuration centralizes existing action targets', function () {
    $actions = file_get_contents(resource_path('js/lib/relationshipActions.ts'));

    expect($actions)
        ->toContain('contactMessageAction')
        ->toContain('followAction')
        ->toContain('sendContactRequestAction')
        ->toContain('acceptContactRequestAction')
        ->toContain('rejectContactRequestAction')
        ->toContain('cancelContactRequestAction')
        ->toContain('removeContactAction')
        ->toContain('blockUserAction')
        ->toContain('unblockUserAction')
        ->toContain("action: '/contact-requests'")
        ->toContain("label: 'Kontaktanfrage senden'")
        ->toContain("label: 'Annehmen'")
        ->toContain("label: 'Ablehnen'")
        ->toContain("label: 'Verbindung entfernen'")
        ->toContain("label: 'Blockierung aufheben'");
});

test('relationship action consumers use the shared configuration for mutations', function () {
    $contactActions = file_get_contents(resource_path('js/components/ContactActions.vue'));
    $blockActions = file_get_contents(resource_path('js/components/BlockActions.vue'));
    $contactRequestIndex = file_get_contents(resource_path('js/pages/ContactRequests/Index.vue'));
    $contactsIndex = file_get_contents(resource_path('js/pages/Contacts/Index.vue'));
    $blockedProfiles = file_get_contents(resource_path('js/pages/BlockedProfiles/Index.vue'));

    expect($contactActions)
        ->toContain('followAction')
        ->toContain('sendContactRequestAction')
        ->toContain('acceptContactRequestAction')
        ->toContain('rejectContactRequestAction')
        ->toContain('relationshipActionUnavailableText')
        ->and($blockActions)
        ->toContain('blockUserAction')
        ->toContain('unblockUserAction')
        ->and($contactRequestIndex)
        ->toContain('acceptContactRequestAction')
        ->toContain('rejectContactRequestAction')
        ->and($contactsIndex)
        ->toContain('contactMessageAction')
        ->toContain('removeContactAction')
        ->and($blockedProfiles)
        ->toContain('unblockUserAction');
});
