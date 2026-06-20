<?php

test('profile page uses one compact header for identity status region and bio', function () {
    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );

    expect($profilePage)
        ->not->toContain("import PageHeader from '@/components/PageHeader.vue'")
        ->not->toContain('<PageHeader')
        ->toContain('max-w-6xl')
        ->toContain('lg:flex-row')
        ->toContain('size-20')
        ->toContain('{{ displayName }}')
        ->toContain('@{{ props.profile.username }}')
        ->toContain('<ContactStatusBadge')
        ->toContain('v-if="props.profile.region"')
        ->toContain('{{ props.profile.region }}')
        ->toContain('v-if="props.profile.bio"')
        ->toContain('{{ props.profile.bio }}')
        ->toContain('whitespace-pre-wrap');
});

test('profile actions keep primary interactions visible and move safety actions into more actions', function () {
    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );
    $moreActions = file_get_contents(
        resource_path('js/components/ProfileMoreActions.vue'),
    );

    expect($profilePage)
        ->toContain('class="flex w-full flex-col gap-2 lg:w-52 lg:shrink-0"')
        ->toContain('<ContactActions')
        ->toContain('<ProfileMoreActions')
        ->not->toContain('<BlockActions')
        ->not->toContain('<ReportDialog')
        ->toContain('Profil bearbeiten')
        ->and($moreActions)
        ->toContain('Weitere Aktionen')
        ->toContain('<ReportDialog')
        ->toContain('<BlockActions')
        ->toContain('Abbrechen');
});

test('profile detail cards use larger chips and a compact two column layout', function () {
    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );

    expect($profilePage)
        ->toContain('class="grid gap-4 md:grid-cols-2"')
        ->toContain('class="flex flex-wrap gap-2.5"')
        ->toContain('px-4 py-2 text-sm')
        ->toContain('Sprachen')
        ->toContain('Interessen');
});
