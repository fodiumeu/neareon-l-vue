<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Smile } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        initialValue?: string | null;
        placeholder?: string;
    }>(),
    {
        initialValue: '',
        placeholder: '',
    },
);

const emojis = [
    '😀',
    '😃',
    '😄',
    '😁',
    '😆',
    '😅',
    '😂',
    '🤣',
    '😊',
    '😇',
    '😍',
    '🥰',
    '😘',
    '😎',
    '🤗',
    '🤔',
    '😴',
    '😢',
    '👍',
    '👏',
    '🙌',
    '❤️',
    '💙',
    '💚',
    '🔥',
    '🎉',
    '🎵',
    '🚀',
];

const bioInput = ref<HTMLTextAreaElement | null>(null);
const emojiPicker = ref<HTMLElement | null>(null);
const isEmojiPickerOpen = ref(false);
const bio = ref(props.initialValue ?? '');

const insertEmoji = async (emoji: string) => {
    const input = bioInput.value;

    if (input === null) {
        return;
    }

    const selectionStart = input.selectionStart ?? input.value.length;
    const selectionEnd = input.selectionEnd ?? selectionStart;
    const nextValue =
        input.value.slice(0, selectionStart) +
        emoji +
        input.value.slice(selectionEnd);

    if (input.maxLength >= 0 && nextValue.length > input.maxLength) {
        input.focus();

        return;
    }

    bio.value = nextValue;
    const nextCursorPosition = selectionStart + emoji.length;

    await nextTick();
    input.focus();
    input.setSelectionRange(nextCursorPosition, nextCursorPosition);
};

onClickOutside(emojiPicker, () => {
    isEmojiPickerOpen.value = false;
});
</script>

<template>
    <div class="grid gap-2">
        <Label for="bio">Bio</Label>

        <textarea
            id="bio"
            ref="bioInput"
            v-model="bio"
            name="bio"
            maxlength="280"
            rows="4"
            class="flex min-h-24 w-full resize-y rounded-md border border-input bg-background/80 px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:border-border/90 dark:bg-input/40"
            :placeholder="placeholder"
        />

        <div ref="emojiPicker" class="relative flex justify-end">
            <Button
                type="button"
                variant="secondary"
                size="sm"
                :aria-expanded="isEmojiPickerOpen"
                aria-controls="bio-emoji-picker"
                aria-label="Emoji für die Bio auswählen"
                data-test="bio-emoji-picker-toggle"
                @click="isEmojiPickerOpen = !isEmojiPickerOpen"
            >
                <Smile aria-hidden="true" />
                Emoji
            </Button>

            <div
                v-if="isEmojiPickerOpen"
                id="bio-emoji-picker"
                class="absolute right-0 bottom-full z-30 mb-2 grid w-64 max-w-[calc(100vw-2rem)] grid-cols-6 gap-1 rounded-lg border border-border bg-popover p-2 text-popover-foreground shadow-xl sm:w-72 sm:grid-cols-7"
                role="dialog"
                aria-label="Emoji-Auswahl für die Bio"
                data-test="bio-emoji-picker-panel"
            >
                <button
                    v-for="(emoji, index) in emojis"
                    :key="`${emoji}-${index}`"
                    type="button"
                    class="flex size-9 items-center justify-center rounded-md text-xl transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Emoji ${emoji} in die Bio einfügen`"
                    @click="insertEmoji(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>
    </div>
</template>
