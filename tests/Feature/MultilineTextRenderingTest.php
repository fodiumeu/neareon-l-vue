<?php

test('profile bios preserve line breaks in discover and profile views', function (
    string $page,
    string $binding,
) {
    $content = file_get_contents(resource_path("js/pages/{$page}"));

    expect($content)
        ->toContain('whitespace-pre-wrap')
        ->toContain($binding)
        ->toContain("{{ {$binding} }}")
        ->not->toContain("v-html=\"{$binding}\"");
})->with([
    'discover bio' => ['Discover.vue', 'profile.bio'],
    'profile bio' => ['Profile/Show.vue', 'props.profile.bio'],
]);

test('user supplied multiline mvp texts use escaped vue output with preserved whitespace', function (
    string $page,
    string $binding,
) {
    $content = file_get_contents(resource_path("js/pages/{$page}"));

    expect($content)
        ->toContain('whitespace-pre-wrap')
        ->toContain("{{ {$binding} }}")
        ->not->toContain("v-html=\"{$binding}\"");
})->with([
    'received contact request message' => [
        'ContactRequests/Index.vue',
        'contactRequest.message',
    ],
    'sent contact request message' => [
        'ContactRequests/Sent.vue',
        'contactRequest.message',
    ],
    'chat message body' => ['Messages/Show.vue', 'message.body'],
]);

test('report descriptions already preserve line breaks without html rendering', function () {
    $content = file_get_contents(
        resource_path('js/pages/admin/Reports/Index.vue'),
    );

    expect($content)
        ->toContain('whitespace-pre-wrap')
        ->toContain('report.description')
        ->not->toContain('v-html="report.description"');
});
