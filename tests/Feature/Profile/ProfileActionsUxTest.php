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
        ->toContain('<BlockActions')
        ->toContain('@success="open = false"')
        ->toContain('Schließen');
});

test('more actions closes after successful block or unblock actions', function () {
    $moreActions = file_get_contents(
        resource_path('js/components/ProfileMoreActions.vue'),
    );
    $blockActions = file_get_contents(
        resource_path('js/components/BlockActions.vue'),
    );

    expect($moreActions)
        ->toContain('const open = ref(false)')
        ->toContain(':open="open"')
        ->toContain('@update:open="open = $event"')
        ->toContain('@success="open = false"')
        ->toContain('Schließen')
        ->and($blockActions)
        ->toContain('const confirmationOpen = ref(false)')
        ->toContain("emit('success')")
        ->toContain('@success="handleSuccess"')
        ->toContain('confirmationOpen.value = false');
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
        ->toContain('blockUserAction(username)')
        ->toContain('unblockUserAction(username)')
        ->and($reportDialog)
        ->toContain(':action="`/u/${username}/reports`"');
});

test('message and unfollow visual priorities remain in contact actions', function () {
    $contactActions = file_get_contents(
        resource_path('js/components/ContactActions.vue'),
    );

    expect($contactActions)
        ->toContain('contactMessageAction(userId)')
        ->toContain(":variant=\"isFollowing ? 'secondary' : 'default'\"")
        ->toContain('followAction(username, isFollowing).label')
        ->toContain('name="from"')
        ->toContain(':value="backContext.from"')
        ->toContain('name="group"')
        ->toContain(':value="backContext.group"');
});
