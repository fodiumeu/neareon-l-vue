<?php

test('own messages remove the du label and use stronger readable styling', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('v-if="!message.is_own"')
        ->not->toContain("message.is_own ? 'Du'")
        ->toContain(
            'bg-action-primary',
        )
        ->toContain('text-action-primary-foreground')
        ->toContain('px-5 py-3 text-base leading-7')
        ->toContain('dark:text-white/70')
        ->toContain('text-[0.8125rem]');
});

test('emoji only timestamps use the standard size with reduced visual weight', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('emojiOnlyCount(message.body)')
        ->toContain("'px-1 text-[0.8125rem]'")
        ->toContain("? 'opacity-60'")
        ->not->toContain('text-[0.6875rem]');
});

test('emoji only messages detect one to three unicode emoji sequences', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('const emojiTokenPattern =')
        ->toContain('\p{Extended_Pictographic}')
        ->toContain('\p{Emoji_Modifier}')
        ->toContain('\p{Regional_Indicator}{2}')
        ->toContain('matches.length > 3')
        ->toContain("matches.map((match) => match[0]).join('') !== body")
        ->toContain('return matches.length');
});

test('emoji only messages render without a bubble at responsive sizes', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('emojiOnlyCount(message.body)')
        ->toContain('emojiOnlySizeClass(message.body)')
        ->toContain(
            'border-0 bg-transparent p-0 leading-none shadow-none',
        )
        ->toContain("return 'text-[2.75rem] sm:text-5xl'")
        ->toContain("return 'text-4xl sm:text-[2.5rem]'")
        ->toContain("return 'text-[2rem] sm:text-[2.2rem]'");
});

test('mixed text and emoji messages keep the normal chat bubble branch', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain(
            'rounded-2xl border px-4 py-2.5 text-sm leading-6',
        )
        ->toContain('[overflow-wrap:anywhere]')
        ->toContain('whitespace-pre-wrap')
        ->toContain('rounded-br-md')
        ->toContain('rounded-bl-md');
});

test('existing mobile composer and chat interactions remain intact', function () {
    $page = file_get_contents(resource_path('js/pages/Messages/Show.vue'));

    expect($page)
        ->toContain('sticky bottom-0')
        ->toContain('data-test="message-composer"')
        ->toContain('@success="handleMessageSent"')
        ->toContain('@keydown="handleMessageKeydown"')
        ->toContain('data-test="emoji-picker-toggle"')
        ->toContain('data-test="conversation-end"');
});
