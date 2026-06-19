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
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ConversationMessage = {
    id: number;
    body: string;
    created_at: string;
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

const avatarInitial = (message: ConversationMessage) =>
    message.sender.display_name.charAt(0).toUpperCase();

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

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

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
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <AppBackButton fallback="/messages" label="Zurück zu den Nachrichten" />

        <PageHeader
            :title="participantLabel"
            :description="
                conversation.other_participant.username
                    ? `@${conversation.other_participant.username}`
                    : 'Unterhaltung'
            "
        />

        <PageSection v-if="conversation.messages.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Es wurden noch keine Nachrichten ausgetauscht.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-4">
                <Card
                    v-for="message in conversation.messages"
                    :key="message.id"
                    class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent class="space-y-3">
                        <div class="flex items-start gap-3">
                            <Avatar
                                class="size-10 shrink-0 border border-primary/25"
                            >
                                <AvatarFallback
                                    class="bg-primary/15 text-sm font-semibold text-primary"
                                >
                                    {{ avatarInitial(message) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">
                                    {{ message.sender.display_name }}
                                </p>
                                <p
                                    v-if="message.sender.username"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ message.sender.username }}
                                </p>
                            </div>

                            <time
                                :datetime="message.created_at"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(message.created_at) }}
                            </time>
                        </div>

                        <p
                            class="rounded-md border border-border bg-background/60 px-4 py-3 text-sm leading-6 whitespace-pre-wrap text-foreground dark:bg-input/20"
                        >
                            {{ message.body }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection v-if="conversation.can_send_messages">
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
                                    class="flex min-h-28 min-w-0 flex-1 resize-none overflow-y-hidden rounded-md border border-input bg-transparent px-3 py-2 text-sm leading-6 shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/20"
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

        <div
            ref="conversationEnd"
            aria-hidden="true"
            data-test="conversation-end"
        />
    </div>
</template>
