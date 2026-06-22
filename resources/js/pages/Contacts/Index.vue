<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';
import ContactStatusBadge from '@/components/ContactStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
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
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import {
    formatContactRelativeTime,
    formatContactRelativeTimeTitle,
} from '@/lib/contactRelativeTime';

type Contact = {
    common_interests: string[];
    common_languages: string[];
    connected_at: string | null;
    conversation_id: number | null;
    display_name: string;
    id: number;
    last_activity_at: string | null;
    profile_photo_url: string | null;
    status: 'connected';
    username: string;
};

defineProps<{
    contacts: Contact[];
}>();

const disconnectingContactId = ref<number | null>(null);

const setDisconnectDialogOpen = (contactId: number, open: boolean) => {
    disconnectingContactId.value = open ? contactId : null;
};

const avatarInitial = (contact: Contact) =>
    contact.display_name.charAt(0).toUpperCase();

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
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
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
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-3 p-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <ProfileAvatar
                                :photo-url="contact.profile_photo_url"
                                :alt="contact.display_name"
                                :fallback="avatarInitial(contact)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

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
                            </div>
                        </div>

                        <ContactStatusBadge
                            :status="contact.status"
                            class="w-fit"
                        />

                        <div
                            v-if="
                                contact.connected_at || contact.last_activity_at
                            "
                            class="space-y-1 text-xs text-muted-foreground"
                        >
                            <p v-if="contact.connected_at">
                                Verbunden seit:
                                <time
                                    :datetime="contact.connected_at"
                                    :title="
                                        formatContactRelativeTimeTitle(
                                            contact.connected_at,
                                        )
                                    "
                                >
                                    {{
                                        formatContactRelativeTime(
                                            contact.connected_at,
                                        )
                                    }}
                                </time>
                            </p>
                            <p v-if="contact.last_activity_at">
                                Letzte Aktivität:
                                <time
                                    :datetime="contact.last_activity_at"
                                    :title="
                                        formatContactRelativeTimeTitle(
                                            contact.last_activity_at,
                                        )
                                    "
                                >
                                    {{
                                        formatContactRelativeTime(
                                            contact.last_activity_at,
                                        )
                                    }}
                                </time>
                            </p>
                        </div>

                        <div
                            v-if="contact.common_languages.length"
                            class="space-y-2"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Gemeinsame Sprachen
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="language in contact.common_languages.slice(
                                        0,
                                        2,
                                    )"
                                    :key="language"
                                    variant="secondary"
                                >
                                    {{ language }}
                                </Badge>
                                <Badge
                                    v-if="contact.common_languages.length > 2"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    +{{ contact.common_languages.length - 2 }}
                                    weitere
                                </Badge>
                            </div>
                        </div>

                        <div
                            v-if="contact.common_interests.length"
                            class="space-y-2"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Gemeinsame Interessen
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="interest in contact.common_interests.slice(
                                        0,
                                        3,
                                    )"
                                    :key="interest"
                                    variant="outline"
                                    class="border-primary/30 bg-primary/10"
                                >
                                    {{ interest }}
                                </Badge>
                                <Badge
                                    v-if="contact.common_interests.length > 3"
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    +{{ contact.common_interests.length - 3 }}
                                    weitere
                                </Badge>
                            </div>
                        </div>

                        <div class="mt-auto grid gap-2 pt-1">
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

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="secondary" class="w-full">
                                        <MoreHorizontal aria-hidden="true" />
                                        Weitere Aktionen
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-56">
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @select="
                                            disconnectingContactId = contact.id
                                        "
                                    >
                                        Verbindung entfernen
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Dialog
                                :open="disconnectingContactId === contact.id"
                                @update:open="
                                    setDisconnectDialogOpen(contact.id, $event)
                                "
                            >
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
