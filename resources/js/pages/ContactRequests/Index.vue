<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type ContactRequest = {
    id: number;
    message: string | null;
    created_at: string;
    sender: {
        display_name: string;
        username: string | null;
    };
};

defineProps<{
    contactRequests: ContactRequest[];
}>();

const avatarInitial = (contactRequest: ContactRequest) =>
    contactRequest.sender.display_name.charAt(0).toUpperCase();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kontaktanfragen',
                href: '/contact-requests',
            },
        ],
    },
});
</script>

<template>
    <Head title="Kontaktanfragen" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Kontaktanfragen"
            description="Hier siehst du offene Kontaktanfragen, die du erhalten hast."
        />

        <PageSection v-if="contactRequests.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Derzeit liegen keine Kontaktanfragen vor.
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
                                    {{ contactRequest.sender.display_name }}
                                </h2>
                                <p
                                    v-if="contactRequest.sender.username"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contactRequest.sender.username }}
                                </p>
                            </div>

                            <time
                                :datetime="contactRequest.created_at"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(contactRequest.created_at) }}
                            </time>
                        </div>

                        <p
                            v-if="contactRequest.message"
                            class="rounded-md border border-border bg-background/60 px-4 py-3 text-sm leading-6 text-foreground dark:bg-input/20"
                        >
                            {{ contactRequest.message }}
                        </p>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Form
                                :action="`/contact-requests/${contactRequest.id}/accept`"
                                method="patch"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="w-full sm:w-auto"
                                >
                                    <Spinner v-if="processing" />
                                    Annehmen
                                </Button>
                            </Form>

                            <Form
                                :action="`/contact-requests/${contactRequest.id}/decline`"
                                method="patch"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    :disabled="processing"
                                    class="w-full sm:w-auto"
                                >
                                    <Spinner v-if="processing" />
                                    Ablehnen
                                </Button>
                            </Form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
