<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
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
    conversation_id: number;
    other_participant: {
        display_name: string | null;
        username: string | null;
    };
    messages: ConversationMessage[];
};

const props = defineProps<{
    conversation: Conversation;
}>();

const participantLabel =
    props.conversation.other_participant.display_name ??
    (props.conversation.other_participant.username
        ? `@${props.conversation.other_participant.username}`
        : 'Unbekannter Teilnehmer');

const avatarInitial = (message: ConversationMessage) =>
    message.sender.display_name.charAt(0).toUpperCase();

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

        <PageSection>
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent>
                    <Form
                        :action="`/messages/${conversation.conversation_id}`"
                        method="post"
                        reset-on-success
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <div class="grid gap-2">
                            <Label for="message">Nachricht</Label>
                            <textarea
                                id="message"
                                name="message"
                                rows="5"
                                maxlength="5000"
                                required
                                class="flex min-h-28 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/20"
                                placeholder="Schreibe eine Nachricht …"
                            />
                            <InputError :message="errors.message" />
                        </div>

                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            Nachricht senden
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
