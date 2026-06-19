<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ContactStatusBadge from '@/components/ContactStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Contact = {
    connected_at: string | null;
    conversation_id: number | null;
    display_name: string;
    id: number;
    last_activity_at: string | null;
    status: 'connected';
    username: string;
};

defineProps<{
    contacts: Contact[];
}>();

const avatarInitial = (contact: Contact) =>
    contact.display_name.charAt(0).toUpperCase();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kontakte',
                href: '/contacts',
            },
        ],
    },
});
</script>

<template>
    <Head title="Kontakte" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Kontakte"
            description="Hier siehst du alle Benutzer, mit denen du gegenseitig verbunden bist."
        />

        <PageSection v-if="contacts.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit noch keine Kontakte.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="contact in contacts"
                    :key="contact.username"
                    class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <Avatar
                                class="size-12 shrink-0 border border-primary/25"
                            >
                                <AvatarFallback
                                    class="bg-primary/15 text-base font-semibold text-primary"
                                >
                                    {{ avatarInitial(contact) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ contact.display_name }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contact.username }}
                                </p>
                                <ContactStatusBadge
                                    :status="contact.status"
                                    class="mt-2"
                                />
                            </div>
                        </div>

                        <div
                            v-if="
                                contact.connected_at || contact.last_activity_at
                            "
                            class="space-y-1 text-xs text-muted-foreground"
                        >
                            <p v-if="contact.connected_at">
                                Verbunden seit:
                                {{ formatDate(contact.connected_at) }}
                            </p>
                            <p v-if="contact.last_activity_at">
                                Letzte Aktivität:
                                {{ formatDate(contact.last_activity_at) }}
                            </p>
                        </div>

                        <div class="mt-auto grid gap-2">
                            <Form
                                :action="`/contacts/${contact.id}/messages`"
                                method="post"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="w-full"
                                >
                                    <Spinner v-if="processing" />
                                    Nachricht senden
                                </Button>
                            </Form>

                            <Button as-child variant="secondary" class="w-full">
                                <Link :href="`/u/${contact.username}`">
                                    Profil ansehen
                                </Link>
                            </Button>

                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        class="w-full"
                                    >
                                        Verbindung entfernen
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <Form
                                        :action="`/contacts/${contact.id}`"
                                        method="delete"
                                        :options="{ preserveScroll: true }"
                                        v-slot="{ processing }"
                                        class="space-y-6"
                                    >
                                        <DialogHeader class="space-y-3">
                                            <DialogTitle>
                                                Verbindung entfernen?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Die Verbindung zu diesem Kontakt
                                                wird aufgehoben. Bereits
                                                ausgetauschte Nachrichten
                                                bleiben erhalten.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <DialogFooter class="gap-2">
                                            <DialogClose as-child>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    :disabled="processing"
                                                >
                                                    Abbrechen
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                :disabled="processing"
                                            >
                                                <Spinner v-if="processing" />
                                                Verbindung entfernen
                                            </Button>
                                        </DialogFooter>
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
