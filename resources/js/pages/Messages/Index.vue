<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    formatMessageTimestamp,
    formatMessageTimestampTitle,
} from '@/lib/messageTimestamp';

type Conversation = {
    conversation_id: number;
    participant_count: number;
    created_at: string;
    updated_at: string;
    unread_count: number;
    last_message: {
        body: string;
        created_at: string;
        is_own: boolean;
    } | null;
    other_participant: {
        display_name: string | null;
        profile_photo_url: string | null;
        username: string | null;
    };
};

type BackLink = {
    href: string;
    label: string;
    source: 'home';
};

defineProps<{
    backLink: BackLink | null;
    conversations: Conversation[];
}>();

const participantLabel = (conversation: Conversation) =>
    conversation.other_participant.display_name ??
    (conversation.other_participant.username
        ? `@${conversation.other_participant.username}`
        : 'Unbekannter Teilnehmer');

const avatarInitial = (conversation: Conversation) =>
    participantLabel(conversation).charAt(0).toUpperCase();

const unreadLabel = (count: number) => (count >= 100 ? '99+' : count);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Unterhaltungen',
                href: '/messages',
            },
        ],
    },
});
</script>

<template>
    <Head title="Unterhaltungen" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <Button
            v-if="backLink"
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link :href="backLink.href" class="min-w-0 truncate">
                ← {{ backLink.label }}
            </Link>
        </Button>

        <PageHeader
            title="Unterhaltungen"
            description="Hier siehst du deine bestehenden Unterhaltungen."
        />

        <PageSection v-if="conversations.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="space-y-4 text-center sm:text-left">
                    <h2 class="font-medium">
                        Du hast aktuell noch keine Nachrichten.
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Öffne das Profil eines Kontakts, um eine Unterhaltung zu
                        starten.
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button as-child>
                            <Link href="/contacts">Kontakte öffnen</Link>
                        </Button>
                        <Button as-child variant="secondary">
                            <Link href="/community">Community öffnen</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-4">
                <Card
                    v-for="conversation in conversations"
                    :key="conversation.conversation_id"
                    :class="[
                        'overflow-hidden bg-card/95 shadow-md shadow-black/5 transition-colors hover:border-primary/35 dark:shadow-black/25',
                        conversation.unread_count > 0
                            ? 'border-primary/40 bg-primary/[0.04]'
                            : null,
                    ]"
                >
                    <Link
                        :href="`/messages/${conversation.conversation_id}`"
                        class="block rounded-lg outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <CardContent class="flex items-center gap-4 p-4 sm:p-5">
                            <ProfileAvatar
                                :photo-url="
                                    conversation.other_participant
                                        .profile_photo_url
                                "
                                :alt="participantLabel(conversation)"
                                :fallback="avatarInitial(conversation)"
                                class="size-14 shrink-0"
                                fallback-class="text-lg"
                            />

                            <div class="min-w-0 flex-1 space-y-1.5">
                                <h2
                                    :class="[
                                        'truncate text-base tracking-tight',
                                        conversation.unread_count > 0
                                            ? 'font-bold text-foreground'
                                            : 'font-semibold',
                                    ]"
                                >
                                    {{ participantLabel(conversation) }}
                                </h2>
                                <p
                                    v-if="
                                        conversation.other_participant.username
                                    "
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{
                                        conversation.other_participant.username
                                    }}
                                </p>
                                <p
                                    v-if="conversation.last_message"
                                    :class="[
                                        'truncate text-sm',
                                        conversation.unread_count > 0
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground',
                                    ]"
                                >
                                    <span
                                        v-if="conversation.last_message.is_own"
                                        >Du:
                                    </span>
                                    {{ conversation.last_message.body }}
                                </p>
                                <p
                                    v-else
                                    class="text-sm text-muted-foreground italic"
                                >
                                    Noch keine Nachrichten
                                </p>
                            </div>

                            <div
                                class="flex shrink-0 flex-col items-end gap-2 self-start"
                            >
                                <time
                                    :datetime="
                                        conversation.last_message?.created_at ??
                                        conversation.updated_at
                                    "
                                    :title="
                                        formatMessageTimestampTitle(
                                            conversation.last_message
                                                ?.created_at ??
                                                conversation.updated_at,
                                        )
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        formatMessageTimestamp(
                                            conversation.last_message
                                                ?.created_at ??
                                                conversation.updated_at,
                                        )
                                    }}
                                </time>
                                <Badge
                                    v-if="conversation.unread_count > 0"
                                    class="min-w-7 px-2 py-1 text-sm font-semibold"
                                    :aria-label="`${conversation.unread_count} ungelesene Nachrichten`"
                                    data-test="conversation-unread-badge"
                                >
                                    {{ unreadLabel(conversation.unread_count) }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Link>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
