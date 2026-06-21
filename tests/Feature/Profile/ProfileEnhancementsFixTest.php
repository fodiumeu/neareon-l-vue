<?php

test('onboarding and profile editing use the shared bio emoji picker', function () {
    $onboarding = file_get_contents(
        resource_path('js/pages/Onboarding/Details.vue'),
    );
    $profileEdit = file_get_contents(
        resource_path('js/pages/Profile/Edit.vue'),
    );

    foreach ([$onboarding, $profileEdit] as $page) {
        expect($page)
            ->toContain("import BioEmojiField from '@/components/BioEmojiField.vue'")
            ->toContain('<BioEmojiField');
    }
});

test('bio emoji picker opens accessibly and stays within mobile viewports', function () {
    $picker = file_get_contents(
        resource_path('js/components/BioEmojiField.vue'),
    );

    expect($picker)
        ->toContain('data-test="bio-emoji-picker-toggle"')
        ->toContain('data-test="bio-emoji-picker-panel"')
        ->toContain(':aria-expanded="isEmojiPickerOpen"')
        ->toContain('aria-controls="bio-emoji-picker"')
        ->toContain('role="dialog"')
        ->toContain('max-w-[calc(100vw-2rem)]')
        ->toContain('onClickOutside(emojiPicker');
});

test('bio emoji insertion uses the cursor position and restores focus', function () {
    $picker = file_get_contents(
        resource_path('js/components/BioEmojiField.vue'),
    );

    expect($picker)
        ->toContain('const selectionStart = input.selectionStart')
        ->toContain('const selectionEnd = input.selectionEnd')
        ->toContain('input.value.slice(0, selectionStart)')
        ->toContain('input.value.slice(selectionEnd)')
        ->toContain('const nextCursorPosition = selectionStart + emoji.length')
        ->toContain('input.focus()')
        ->toContain(
            'input.setSelectionRange(nextCursorPosition, nextCursorPosition)',
        );
});

test('mobile lightbox background closes while image interactions stay open', function () {
    $lightbox = file_get_contents(
        resource_path('js/components/ProfilePhotoLightbox.vue'),
    );

    expect($lightbox)
        ->toContain('v-model:open="isOpen"')
        ->toContain('data-test="profile-photo-lightbox-overlay"')
        ->toContain('@click.self="isOpen = false"')
        ->toContain('data-test="profile-photo-lightbox-image-container"')
        ->toContain('@click.stop')
        ->toContain('data-test="profile-photo-lightbox-image"');
});

test('lightbox keeps escape close focus management and initials fallback', function () {
    $lightbox = file_get_contents(
        resource_path('js/components/ProfilePhotoLightbox.vue'),
    );

    expect($lightbox)
        ->toContain('<Dialog v-if="photoUrl"')
        ->toContain('<DialogTrigger as-child>')
        ->toContain('<DialogClose')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('aria-label="Profilbild schließen"')
        ->toContain('<ProfileAvatar')
        ->toContain('v-else');
});
