<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Conversation = {
    conversation_id: number;
    participant_count: number;
    created_at: string;
    updated_at: string;
    other_participant: {
        display_name: string | null;
        username: string | null;
    };
};

defineProps<{
    conversations: Conversation[];
}>();

const participantLabel = (conversation: Conversation) =>
    conversation.other_participant.display_name ??
    (conversation.other_participant.username
        ? `@${conversation.other_participant.username}`
        : 'Unbekannter Teilnehmer');

const avatarInitial = (conversation: Conversation) =>
    participantLabel(conversation).charAt(0).toUpperCase();

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
        ],
    },
});
</script>

<template>
    <Head title="Unterhaltungen" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Unterhaltungen"
            description="Hier siehst du deine bestehenden Unterhaltungen."
        />

        <PageSection v-if="conversations.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit noch keine Unterhaltungen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-4">
                <Card
                    v-for="conversation in conversations"
                    :key="conversation.conversation_id"
                    class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent class="space-y-4">
                        <div class="flex items-start gap-3">
                            <Avatar
                                class="size-12 shrink-0 border border-primary/25"
                            >
                                <AvatarFallback
                                    class="bg-primary/15 text-base font-semibold text-primary"
                                >
                                    {{ avatarInitial(conversation) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
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
                            </div>

                            <time
                                :datetime="conversation.updated_at"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(conversation.updated_at) }}
                            </time>
                        </div>

                        <div
                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="rounded-full border border-border bg-background/70 px-3 py-1 dark:bg-input/30"
                            >
                                Teilnehmer:
                                {{ conversation.participant_count }}
                            </span>
                            <span>
                                Letzte Aktivität:
                                {{ formatDate(conversation.updated_at) }}
                            </span>
                        </div>

                        <Button as-child variant="secondary" class="w-full">
                            <Link
                                :href="`/messages/${conversation.conversation_id}`"
                            >
                                Unterhaltung öffnen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
