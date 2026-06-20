<?php

test('profile safety actions are grouped behind a secondary more actions trigger', function () {
    $component = file_get_contents(
        resource_path('js/components/ProfileMoreActions.vue'),
    );

    expect($component)
        ->toContain('<MoreHorizontal')
        ->toContain('variant="secondary"')
        ->toContain('Weitere Aktionen')
        ->toContain('<ReportDialog :username="username"')
        ->toContain('<BlockActions :is-blocked="isBlocked"')
        ->toContain('Abbrechen');
});

test('existing report and block components remain the action implementations', function () {
    $component = file_get_contents(
        resource_path('js/components/ProfileMoreActions.vue'),
    );
    $blockActions = file_get_contents(
        resource_path('js/components/BlockActions.vue'),
    );
    $reportDialog = file_get_contents(
        resource_path('js/components/ReportDialog.vue'),
    );

    expect($component)
        ->not->toContain('/block')
        ->not->toContain('/reports')
        ->and($blockActions)
        ->toContain(':action="`/u/${username}/block`"')
        ->and($reportDialog)
        ->toContain(':action="`/u/${username}/reports`"');
});

test('message and unfollow visual priorities remain in contact actions', function () {
    $contactActions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );

    expect($contactActions)
        ->toContain('Nachricht senden')
        ->toContain(":variant=\"isFollowing ? 'secondary' : 'default'\"")
        ->toContain("{{ isFollowing ? 'Entfolgen' : 'Folgen' }}");
});
