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
        ->toContain('size-24')
        ->toContain('lg:size-28')
        ->toContain('{{ displayName }}')
        ->toContain('@{{ props.profile.username }}')
        ->toContain('v-if="hasVisibleSocialCounts"')
        ->toContain('whitespace-nowrap')
        ->toContain('text-base font-semibold')
        ->toContain('props.profile.followers_count')
        ->toContain('props.profile.contacts_count')
        ->toContain('Mitglied seit')
        ->toContain('Gemeinsame Sprache:')
        ->toContain('Gemeinsame Sprachen:')
        ->toContain('Gemeinsame Interessen:')
        ->toContain('<Badge')
        ->toContain('variant="secondary"')
        ->toContain('variant="outline"')
        ->toContain('Dieses Mitglied hat noch keine Bio')
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
        ->toContain('Schließen');
});

test('profile details combine languages and interests in one compact card', function () {
    $profilePage = file_get_contents(
        resource_path('js/pages/Profile/Show.vue'),
    );

    expect($profilePage)
        ->toContain('Interessen &amp; Sprachen')
        ->toContain('class="space-y-4 p-4 sm:p-5"')
        ->toContain('border-t border-border/70 pt-4')
        ->toContain('class="flex min-w-0 flex-wrap gap-2"')
        ->toContain('px-4 py-2 text-sm')
        ->toContain('Sprachen')
        ->toContain('Interessen')
        ->not->toContain('class="grid gap-4 md:grid-cols-2"');
});
