<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ContactRequestStatusBadge from '@/components/ContactRequestStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import type { ContactRequestStatus } from '@/types';

type SentContactRequest = {
    id: number;
    message: string | null;
    status: ContactRequestStatus;
    created_at: string;
    receiver: {
        display_name: string;
        username: string | null;
    };
};

defineProps<{
    contactRequests: SentContactRequest[];
}>();

const avatarInitial = (contactRequest: SentContactRequest) =>
    contactRequest.receiver.display_name.charAt(0).toUpperCase();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gesendete Kontaktanfragen',
                href: '/contact-requests/sent',
            },
        ],
    },
});
</script>

<template>
    <Head title="Gesendete Kontaktanfragen" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Gesendete Kontaktanfragen"
            description="Hier siehst du alle Kontaktanfragen, die du gesendet hast."
        />

        <PageSection v-if="contactRequests.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit keine gesendeten Kontaktanfragen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-4">
                <Card
                    v-for="contactRequest in contactRequests"
                    :key="contactRequest.id"
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
                                    {{ avatarInitial(contactRequest) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ contactRequest.receiver.display_name }}
                                </h2>
                                <p
                                    v-if="contactRequest.receiver.username"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contactRequest.receiver.username }}
                                </p>
                            </div>

                            <time
                                :datetime="contactRequest.created_at"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(contactRequest.created_at) }}
                            </time>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <ContactRequestStatusBadge
                                :status="contactRequest.status"
                            />
                        </div>

                        <p
                            v-if="contactRequest.message"
                            class="rounded-md border border-border bg-background/60 px-4 py-3 text-sm leading-6 text-foreground dark:bg-input/20"
                        >
                            {{ contactRequest.message }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
