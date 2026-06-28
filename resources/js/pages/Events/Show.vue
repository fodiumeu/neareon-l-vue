<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
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
    DialogTrigger,
} from '@/components/ui/dialog';

type EventDetail = {
    id: number;
    title: string;
    slug: string;
    description?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    visibility: 'public' | 'request';
    visibility_label: string;
    status: 'active' | 'cancelled';
    status_label: string;
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    max_attendees?: number | null;
    owner: {
        name: string;
        username?: string | null;
    };
    attendee_count: number;
    can_edit: boolean;
    edit_url?: string | null;
    back_url: string;
    back_label: string;
    is_full: boolean;
    viewer_attendance_status: 'active' | 'pending' | null;
    viewer_event_role: 'owner' | 'attendee' | 'pending' | 'none';
    can_join: boolean;
    can_request: boolean;
    can_leave: boolean;
    attendance_store_url?: string | null;
    attendance_destroy_url?: string | null;
};

defineProps<{
    event: EventDetail;
}>();

const visibilityBadgeClass = (visibility: EventDetail['visibility']) =>
    visibility === 'request'
        ? 'border-primary/30 bg-primary/10 text-primary'
        : 'border-primary/30 bg-primary/10 text-primary';

const statusBadgeClass = (status: EventDetail['status']) =>
    status === 'cancelled'
        ? 'border-destructive/30 bg-destructive/10 text-destructive'
        : 'border-border bg-background/70 text-muted-foreground dark:bg-input/30';

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const locationLabel = (event: EventDetail) =>
    [event.postal_code, event.region].filter(Boolean).join(' ');

const participationLabel = (event: EventDetail) => {
    if (event.viewer_event_role === 'owner') {
        return 'Veranstalter';
    }

    if (event.viewer_attendance_status === 'active') {
        return 'Teilnehmer';
    }

    if (event.viewer_attendance_status === 'pending') {
        return 'Anfrage gesendet';
    }

    if (event.is_full) {
        return 'Ausgebucht';
    }

    return null;
};

const participationText = (event: EventDetail) => {
    if (event.viewer_event_role === 'owner') {
        return 'Du bist Veranstalter dieses Events.';
    }

    if (event.viewer_attendance_status === 'active') {
        return 'Du nimmst an diesem Event teil.';
    }

    if (event.viewer_attendance_status === 'pending') {
        return 'Deine Teilnahme-Anfrage wartet auf Bestätigung.';
    }

    if (event.is_full) {
        return 'Dieses Event ist bereits ausgebucht.';
    }

    if (event.visibility === 'request') {
        return 'Sende eine Teilnahme-Anfrage, um an diesem Event teilzunehmen.';
    }

    return 'Du kannst direkt an diesem Event teilnehmen.';
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/events',
            },
        ],
    },
});
</script>

<template>
    <Head :title="event.title" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <Button
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link :href="event.back_url" class="min-w-0 truncate">
                ← {{ event.back_label }}
            </Link>
        </Button>

        <div
            class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <PageHeader
                :title="event.title"
                description="Regionales Event aus der NEAREON-Community."
            />

            <Button
                v-if="event.can_edit && event.edit_url"
                as-child
                variant="secondary"
                class="w-full sm:w-auto"
            >
                <Link :href="event.edit_url">Event bearbeiten</Link>
            </Button>
        </div>

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="space-y-5 p-5">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">
                            Eventinformationen
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Die wichtigsten Angaben zu diesem Event auf einen
                            Blick.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Badge
                            variant="outline"
                            :class="visibilityBadgeClass(event.visibility)"
                        >
                            {{ event.visibility_label }}
                        </Badge>
                        <Badge
                            variant="outline"
                            :class="statusBadgeClass(event.status)"
                        >
                            {{ event.status_label }}
                        </Badge>
                        <Badge
                            v-if="event.category"
                            variant="outline"
                            class="max-w-full border-primary/30 bg-primary/10 text-primary"
                        >
                            {{ event.category.label }}
                        </Badge>
                    </div>

                    <div class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">Start</p>
                            <p class="mt-1 break-words font-medium">
                                {{ formatDateTime(event.starts_at) }}
                            </p>
                        </div>

                        <div
                            v-if="event.ends_at"
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">Ende</p>
                            <p class="mt-1 break-words font-medium">
                                {{ formatDateTime(event.ends_at) }}
                            </p>
                        </div>

                        <div
                            v-if="event.region || event.postal_code || event.country_code"
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">
                                Standort
                            </p>
                            <p
                                v-if="event.region || event.postal_code"
                                class="mt-1 break-words font-medium"
                            >
                                {{ locationLabel(event) }}
                            </p>
                            <p
                                v-if="event.country_code"
                                class="text-xs text-muted-foreground"
                            >
                                {{ event.country_code }}
                            </p>
                        </div>

                        <div
                            v-if="event.max_attendees"
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">
                                Maximale Teilnehmerzahl
                            </p>
                            <p class="mt-1 break-words font-medium">
                                {{ event.max_attendees }}
                            </p>
                        </div>

                        <div
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">
                                Ersteller
                            </p>
                            <p class="mt-1 break-words font-medium">
                                {{ event.owner.name }}
                            </p>
                            <p
                                v-if="event.owner.username"
                                class="text-xs text-muted-foreground"
                            >
                                @{{ event.owner.username }}
                            </p>
                        </div>

                        <div
                            class="min-w-0 rounded-lg border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <p class="text-sm text-muted-foreground">
                                Teilnehmer
                            </p>
                            <p class="mt-1 break-words font-medium">
                                {{ event.attendee_count }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <Card>
                <CardContent class="space-y-4 p-5">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">Beschreibung</h2>
                    </div>

                    <p
                        v-if="event.description"
                        class="max-w-4xl text-sm leading-6 break-words whitespace-pre-wrap text-muted-foreground sm:text-base"
                    >
                        {{ event.description }}
                    </p>
                    <p
                        v-else
                        class="rounded-md border border-dashed border-border/80 bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
                    >
                        Dieses Event hat noch keine Beschreibung.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <Card class="border-primary/15 bg-card/80">
                <CardContent class="space-y-4 p-5">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold">
                                Teilnahme
                            </h2>
                            <Badge
                                v-if="participationLabel(event)"
                                variant="outline"
                                class="border-primary/30 bg-primary/10 text-primary"
                            >
                                {{ participationLabel(event) }}
                            </Badge>
                        </div>

                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ participationText(event) }}
                        </p>
                    </div>

                    <div
                        class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <Form
                            v-if="(event.can_join || event.can_request) && event.attendance_store_url"
                            :action="event.attendance_store_url"
                            method="post"
                            class="w-full sm:w-auto"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="processing"
                            >
                                {{
                                    processing
                                        ? 'Wird verarbeitet...'
                                        : event.can_join
                                          ? 'Teilnehmen'
                                          : 'Teilnahme anfragen'
                                }}
                            </Button>
                        </Form>

                        <Dialog
                            v-if="event.can_leave && event.attendance_destroy_url && event.viewer_attendance_status === 'active'"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10 sm:w-auto"
                                >
                                    Teilnahme absagen
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    :action="event.attendance_destroy_url"
                                    method="delete"
                                    v-slot="{ processing }"
                                    class="space-y-6"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Teilnahme absagen?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Du wirst aus der Teilnehmerliste
                                            dieses Events entfernt.
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
                                            variant="outline"
                                            class="border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10"
                                            :disabled="processing"
                                        >
                                            {{
                                                processing
                                                    ? 'Wird verarbeitet...'
                                                    : 'Teilnahme absagen'
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Dialog
                            v-else-if="event.can_leave && event.attendance_destroy_url && event.viewer_attendance_status === 'pending'"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10 sm:w-auto"
                                >
                                    Anfrage zurückziehen
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    :action="event.attendance_destroy_url"
                                    method="delete"
                                    v-slot="{ processing }"
                                    class="space-y-6"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Anfrage zurückziehen?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Deine Teilnahme-Anfrage wird
                                            zurückgezogen.
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
                                            variant="outline"
                                            class="border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10"
                                            :disabled="processing"
                                        >
                                            {{
                                                processing
                                                    ? 'Wird verarbeitet...'
                                                    : 'Anfrage zurückziehen'
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
