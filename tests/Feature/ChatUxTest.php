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

test('conversation provides an accessible responsive emoji picker without external emoji libraries', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain("import { onClickOutside } from '@vueuse/core'")
        ->toContain("import { Smile } from 'lucide-vue-next'")
        ->toContain('data-test="emoji-picker-toggle"')
        ->toContain('data-test="emoji-picker-panel"')
        ->toContain('aria-label="Emoji auswählen"')
        ->toContain('max-w-[calc(100vw-2rem)]')
        ->toContain('grid-cols-6')
        ->toContain('grid-cols-7')
        ->toContain('sm:w-72')
        ->toContain('onClickOutside(emojiPicker')
        ->toContain('isEmojiPickerOpen.value = false');
});

test('emoji insertion uses the current cursor position and preserves input focus', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('const selectionStart = input.selectionStart')
        ->toContain('const selectionEnd = input.selectionEnd')
        ->toContain('input.value.slice(0, selectionStart)')
        ->toContain('input.value.slice(selectionEnd)')
        ->toContain("input.dispatchEvent(new Event('input', { bubbles: true }))")
        ->toContain('input.focus()')
        ->toContain('input.setSelectionRange(nextCursorPosition, nextCursorPosition)')
        ->not->toContain('emoji-mart')
        ->not->toContain('picker-element');
});

test('emoji picker contains the approved mvp emoji set', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    foreach ([
        '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇',
        '😍 🥰 😘 😍 😎 🤗 🤔 😴 😢',
        '👍 👎 👏 🙌 ❤️ 💙 💚 🔥 🎉',
    ] as $emojiRow) {
        foreach (explode(' ', $emojiRow) as $emoji) {
            expect($conversationPage)->toContain("'{$emoji}'");
        }
    }
});

test('enter submits through the existing form while shift enter keeps a line break', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('@keydown="handleMessageKeydown"')
        ->toContain("event.key !== 'Enter'")
        ->toContain('event.shiftKey')
        ->toContain('event.isComposing')
        ->toContain('event.preventDefault()')
        ->toContain('form?.requestSubmit()');
});

test('blank messages cannot be submitted from keyboard or button', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('messageContent.value.trim().length > 0')
        ->toContain('if (!canSendMessage.value)')
        ->toContain(':disabled="processing || !canSendMessage"');
});

test('message input grows to five lines and resets after successful sending', function () {
    $conversationPage = file_get_contents(
        resource_path('js/pages/Messages/Show.vue'),
    );

    expect($conversationPage)
        ->toContain('@input="handleMessageInput"')
        ->toContain('input.scrollHeight')
        ->toContain('lineHeight * 5')
        ->toContain('input.style.overflowY =')
        ->toContain('window.getComputedStyle(input).minHeight')
        ->toContain('input.style.height = `${minimumHeight}px`')
        ->toContain('input.scrollTop = 0')
        ->toContain("messageInput.value.value = ''")
        ->toContain("messageContent.value = ''")
        ->toContain('resetMessageInputHeight()')
        ->toContain('messageInput.value?.focus()')
        ->toContain('resizeMessageInput()')
        ->toContain('rows="1"');
});
