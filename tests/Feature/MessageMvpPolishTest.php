<?php

test('message overview presents profile photo preview timestamp and unread emphasis', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Index.vue'));

    expect($page)
        ->toContain('<ProfileAvatar')
        ->toContain('conversation.last_message.body')
        ->toContain('conversation.last_message.is_own')
        ->toContain('formatMessageTimestamp(')
        ->toContain('formatMessageTimestampTitle(')
        ->toContain('data-test="conversation-unread-badge"')
        ->toContain("'border-primary/40 bg-primary/[0.04]'")
        ->toContain('font-bold text-foreground')
        ->toContain('focus-visible:ring-[3px]');
});

test('message timestamp formatter uses compact german calendar labels without seconds', function () {
    $formatter = file_get_contents(
        resource_path('js/lib/messageTimestamp.ts'),
    );

    expect($formatter)
        ->toContain("return 'Gestern'")
        ->toContain("weekday: 'long'")
        ->toContain("hour: '2-digit'")
        ->toContain("minute: '2-digit'")
        ->toContain("day: '2-digit'")
        ->toContain("month: '2-digit'")
        ->toContain("year: 'numeric'")
        ->not->toContain('second');
});

test('conversation view has participant header and distinct accessible message bubbles', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('<header')
        ->toContain('<ProfileAvatar')
        ->toContain('conversation.other_participant.profile_photo_url')
        ->toContain('aria-label="Nachrichtenverlauf"')
        ->toContain("message.is_own ? 'self-end' : 'self-start'")
        ->toContain('v-if="!message.is_own"')
        ->toContain('{{ message.sender.display_name }}')
        ->toContain('rounded-br-md')
        ->toContain('rounded-bl-md')
        ->toContain('[overflow-wrap:anywhere]')
        ->toContain('whitespace-pre-wrap');
});

test('message empty states use friendly mvp guidance', function () {
    $index = file_get_contents(resource_path('js/pages/Messages/Index.vue'));
    $show = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($index)
        ->toContain('Du hast aktuell noch keine Nachrichten.')
        ->toContain(
            'Öffne das Profil eines Kontakts, um eine Unterhaltung',
        )
        ->and($show)
        ->toContain('Hier erscheinen eure Nachrichten.')
        ->toContain('Schreibe eine Nachricht, um die Unterhaltung zu');
});

test('mobile composer remains reachable with safe area spacing', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('data-test="message-composer"')
        ->toContain('sticky bottom-0')
        ->toContain('env(safe-area-inset-bottom)')
        ->toContain('backdrop-blur')
        ->toContain('min-h-12')
        ->toContain('overflow-x-hidden');
});
