<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { Smile } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AppBackButton from '@/components/AppBackButton.vue';
import InputError from '@/components/InputError.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    formatMessageTimestamp,
    formatMessageTimestampTitle,
} from '@/lib/messageTimestamp';

type ConversationMessage = {
    id: number;
    body: string;
    created_at: string;
    is_own: boolean;
    sender: {
        display_name: string;
        username: string | null;
    };
};

type Conversation = {
    can_send_messages: boolean;
    conversation_id: number;
    is_blocked: boolean;
    other_participant: {
        display_name: string | null;
        profile_photo_url: string | null;
        username: string | null;
    };
    messages: ConversationMessage[];
};

const props = defineProps<{
    conversation: Conversation;
}>();

const conversationEnd = ref<HTMLElement | null>(null);
const messageInput = ref<HTMLTextAreaElement | null>(null);
const emojiPicker = ref<HTMLElement | null>(null);
const isEmojiPickerOpen = ref(false);
const messageContent = ref('');
const previousScrollRestoration = window.history.scrollRestoration;
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
    '😍',
    '😎',
    '🤗',
    '🤔',
    '😴',
    '😢',
    '👍',
    '👎',
    '👏',
    '🙌',
    '❤️',
    '💙',
    '💚',
    '🔥',
    '🎉',
];

window.history.scrollRestoration = 'manual';

const participantLabel =
    props.conversation.other_participant.display_name ??
    (props.conversation.other_participant.username
        ? `@${props.conversation.other_participant.username}`
        : 'Unbekannter Teilnehmer');

const participantInitial = participantLabel.charAt(0).toUpperCase();
const emojiTokenPattern =
    /(?:\p{Extended_Pictographic}(?:\uFE0E|\uFE0F)?(?:\p{Emoji_Modifier})?(?:\u200D\p{Extended_Pictographic}(?:\uFE0E|\uFE0F)?(?:\p{Emoji_Modifier})?)*|\p{Regional_Indicator}{2}|[#*0-9]\uFE0F?\u20E3)/gu;

const emojiOnlyCount = (body: string): number | null => {
    const matches = Array.from(body.matchAll(emojiTokenPattern));

    if (
        matches.length === 0 ||
        matches.length > 3 ||
        matches.map((match) => match[0]).join('') !== body
    ) {
        return null;
    }

    return matches.length;
};

const emojiOnlySizeClass = (body: string) => {
    switch (emojiOnlyCount(body)) {
        case 1:
            return 'text-[2.75rem] sm:text-5xl';
        case 2:
            return 'text-4xl sm:text-[2.5rem]';
        case 3:
            return 'text-[2rem] sm:text-[2.2rem]';
        default:
            return null;
    }
};

const latestMessageId = computed(
    () =>
        props.conversation.messages[props.conversation.messages.length - 1]
            ?.id ?? null,
);
const canSendMessage = computed(() => messageContent.value.trim().length > 0);

const scrollToConversationEnd = async (behavior: ScrollBehavior = 'smooth') => {
    await nextTick();
    conversationEnd.value?.scrollIntoView({
        behavior,
        block: 'end',
    });
};

const waitForNextPaint = () =>
    new Promise<void>((resolve) => {
        window.requestAnimationFrame(() => resolve());
    });

const scrollToInitialConversationEnd = async () => {
    await nextTick();
    await document.fonts.ready;
    await waitForNextPaint();
    await waitForNextPaint();
    await scrollToConversationEnd('auto');
};

const handleMessageSent = async () => {
    isEmojiPickerOpen.value = false;
    messageContent.value = '';

    if (messageInput.value !== null) {
        messageInput.value.value = '';
    }

    resetMessageInputHeight();
    await scrollToConversationEnd();
    messageInput.value?.focus();
};

const resizeMessageInput = () => {
    const input = messageInput.value;

    if (input === null) {
        return;
    }

    input.style.height = 'auto';

    const styles = window.getComputedStyle(input);
    const lineHeight = Number.parseFloat(styles.lineHeight) || 24;
    const verticalPadding =
        Number.parseFloat(styles.paddingTop) +
        Number.parseFloat(styles.paddingBottom);
    const verticalBorder =
        Number.parseFloat(styles.borderTopWidth) +
        Number.parseFloat(styles.borderBottomWidth);
    const minimumHeight = Number.parseFloat(styles.minHeight) || 0;
    const maximumHeight = Math.max(
        minimumHeight,
        lineHeight * 5 + verticalPadding + verticalBorder,
    );
    const nextHeight = Math.min(input.scrollHeight, maximumHeight);

    input.style.height = `${Math.max(nextHeight, minimumHeight)}px`;
    input.style.overflowY =
        input.scrollHeight > maximumHeight ? 'auto' : 'hidden';
};

const resetMessageInputHeight = () => {
    const input = messageInput.value;

    if (input === null) {
        return;
    }

    const minimumHeight =
        Number.parseFloat(window.getComputedStyle(input).minHeight) || 0;

    input.style.height = `${minimumHeight}px`;
    input.style.overflowY = 'hidden';
    input.scrollTop = 0;
};

const handleMessageInput = (event: Event) => {
    messageContent.value = (event.currentTarget as HTMLTextAreaElement).value;
    resizeMessageInput();
};

const handleMessageKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();

    if (!canSendMessage.value) {
        return;
    }

    (event.currentTarget as HTMLTextAreaElement).form?.requestSubmit();
};

const toggleEmojiPicker = () => {
    isEmojiPickerOpen.value = !isEmojiPickerOpen.value;
};

const insertEmoji = async (emoji: string) => {
    const input = messageInput.value;

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

    input.value = nextValue;
    input.dispatchEvent(new Event('input', { bubbles: true }));

    const nextCursorPosition = selectionStart + emoji.length;

    await nextTick();
    input.focus();
    input.setSelectionRange(nextCursorPosition, nextCursorPosition);
};

onClickOutside(emojiPicker, () => {
    isEmojiPickerOpen.value = false;
});

onMounted(() => {
    resizeMessageInput();
    void scrollToInitialConversationEnd();
});

onBeforeUnmount(() => {
    window.history.scrollRestoration = previousScrollRestoration;
});

watch(latestMessageId, (current, previous) => {
    if (current !== previous) {
        void scrollToConversationEnd();
    }
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Unterhaltungen',
                href: '/messages',
            },
            {
                title: 'Unterhaltung',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Unterhaltung mit ${participantLabel}`" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 overflow-x-hidden p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:gap-6 sm:p-6"
    >
        <AppBackButton
            fallback="/messages"
            label="Zurück zu den Nachrichten"
            class="hidden md:inline-flex"
        />

        <header
            class="flex items-center gap-3 rounded-xl border border-border bg-card/95 p-3 shadow-sm sm:p-4"
        >
            <ProfileAvatar
                :photo-url="conversation.other_participant.profile_photo_url"
                :alt="participantLabel"
                :fallback="participantInitial"
                class="size-12 shrink-0"
                fallback-class="text-base"
            />
            <div class="min-w-0">
                <h1 class="truncate text-lg font-semibold tracking-tight">
                    {{ participantLabel }}
                </h1>
                <p
                    v-if="conversation.other_participant.username"
                    class="truncate text-sm text-muted-foreground"
                >
                    @{{ conversation.other_participant.username }}
                </p>
            </div>
        </header>

        <PageSection v-if="conversation.messages.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="space-y-2 text-center sm:text-left">
                    <h2 class="font-medium">
                        Hier erscheinen eure Nachrichten.
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Schreibe eine Nachricht, um die Unterhaltung zu
                        beginnen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="flex flex-col gap-3" aria-label="Nachrichtenverlauf">
                <article
                    v-for="message in conversation.messages"
                    :key="message.id"
                    :class="[
                        'flex max-w-[88%] flex-col gap-1 sm:max-w-[75%]',
                        message.is_own ? 'self-end' : 'self-start',
                    ]"
                >
                    <p
                        v-if="!message.is_own"
                        class="px-1 text-xs font-medium text-muted-foreground"
                    >
                        {{ message.sender.display_name }}
                    </p>
                    <p
                        :class="[
                            emojiOnlyCount(message.body)
                                ? [
                                      'border-0 bg-transparent p-0 leading-none shadow-none',
                                      emojiOnlySizeClass(message.body),
                                  ]
                                : [
                                      'rounded-2xl border px-4 py-2.5 text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap shadow-sm',
                                      message.is_own
                                          ? 'rounded-br-md border-primary/30 bg-[color-mix(in_oklab,var(--primary),black_12%)] px-5 py-3 text-base leading-7 text-white'
                                          : 'rounded-bl-md border-border bg-card text-card-foreground',
                                  ],
                        ]"
                    >
                        {{ message.body }}
                    </p>
                    <time
                        :datetime="message.created_at"
                        :title="formatMessageTimestampTitle(message.created_at)"
                        :class="[
                            'px-1 text-[0.8125rem]',
                            emojiOnlyCount(message.body) ? 'opacity-60' : null,
                            message.is_own
                                ? 'text-right text-foreground/70 dark:text-white/70'
                                : 'text-left text-muted-foreground',
                        ]"
                    >
                        {{ formatMessageTimestamp(message.created_at) }}
                    </time>
                </article>
            </div>
        </PageSection>

        <div
            ref="conversationEnd"
            aria-hidden="true"
            data-test="conversation-end"
        />

        <PageSection
            v-if="conversation.can_send_messages"
            class="sticky bottom-0 z-20 -mx-4 mt-auto border-t border-border/70 bg-background/90 px-4 pt-3 pb-[calc(0.25rem+env(safe-area-inset-bottom))] backdrop-blur sm:mx-0 sm:rounded-xl sm:border"
            data-test="message-composer"
        >
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent>
                    <Form
                        :action="`/messages/${conversation.conversation_id}`"
                        method="post"
                        reset-on-success
                        @success="handleMessageSent"
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <div class="grid gap-2">
                            <Label for="message">Nachricht</Label>
                            <div class="flex items-end gap-2">
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="1"
                                    maxlength="5000"
                                    required
                                    ref="messageInput"
                                    class="flex min-h-12 min-w-0 flex-1 resize-none overflow-y-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-6 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/20"
                                    placeholder="Schreibe eine Nachricht …"
                                    @input="handleMessageInput"
                                    @keydown="handleMessageKeydown"
                                />

                                <div
                                    ref="emojiPicker"
                                    class="relative shrink-0"
                                >
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="icon"
                                        :aria-expanded="isEmojiPickerOpen"
                                        aria-label="Emoji auswählen"
                                        aria-controls="message-emoji-picker"
                                        data-test="emoji-picker-toggle"
                                        @click="toggleEmojiPicker"
                                    >
                                        <Smile aria-hidden="true" />
                                    </Button>

                                    <div
                                        v-if="isEmojiPickerOpen"
                                        id="message-emoji-picker"
                                        class="absolute right-0 bottom-full z-20 mb-2 grid w-64 max-w-[calc(100vw-2rem)] grid-cols-6 gap-1 rounded-lg border border-border bg-popover p-2 text-popover-foreground shadow-xl sm:w-72 sm:grid-cols-7"
                                        role="dialog"
                                        aria-label="Emoji-Auswahl"
                                        data-test="emoji-picker-panel"
                                    >
                                        <button
                                            v-for="(emoji, index) in emojis"
                                            :key="`${emoji}-${index}`"
                                            type="button"
                                            class="flex size-8 items-center justify-center rounded-md text-xl transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:size-9"
                                            :aria-label="`Emoji ${emoji} einfügen`"
                                            @click="insertEmoji(emoji)"
                                        >
                                            {{ emoji }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="errors.message" />
                        </div>

                        <Button
                            type="submit"
                            :disabled="processing || !canSendMessage"
                        >
                            <Spinner v-if="processing" />
                            Nachricht senden
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <Card
                class="border-destructive/30 bg-destructive/5 shadow-md shadow-black/5 dark:bg-destructive/10 dark:shadow-black/25"
            >
                <CardContent>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{
                            conversation.is_blocked
                                ? 'Dieser Benutzer wurde blockiert. Neue Nachrichten sind nicht möglich.'
                                : 'Diese Verbindung wurde beendet. Für neue Nachrichten ist eine neue Verbindung erforderlich.'
                        }}
                    </p>
                    <textarea
                        disabled
                        rows="5"
                        class="mt-4 flex min-h-28 w-full resize-none rounded-md border border-input bg-muted/40 px-3 py-2 text-sm opacity-60"
                        aria-label="Nachricht"
                    />
                    <Button disabled class="mt-4"> Nachricht senden </Button>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
