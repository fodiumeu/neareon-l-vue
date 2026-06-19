<?php

test('conversation view keeps the latest message and composer in view', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('const latestMessageId = computed(')
        ->toContain('watch(latestMessageId')
        ->toContain("behavior: ScrollBehavior = 'smooth'")
        ->toContain("block: 'end'")
        ->toContain('@success="handleMessageSent"')
        ->toContain('messageInput.value?.focus()')
        ->toContain('data-test="conversation-end"');
});

test('conversation scrolling reacts to repeated message updates', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('if (current !== previous)')
        ->toContain('void scrollToConversationEnd()')
        ->toContain('reset-on-success');
});

test('initial conversation scroll waits for stable rendering without timeout hacks', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain("window.history.scrollRestoration = 'manual'")
        ->toContain('await nextTick()')
        ->toContain('await document.fonts.ready')
        ->toContain('window.requestAnimationFrame')
        ->toContain('await waitForNextPaint()')
        ->toContain("await scrollToConversationEnd('auto')")
        ->toContain('window.history.scrollRestoration = previousScrollRestoration')
        ->not->toContain('setTimeout');
});
