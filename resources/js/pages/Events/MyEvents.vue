<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type MyEventSummary = {
    id: number;
    title: string;
    slug: string;
    show_url: string;
    my_events_show_url: string;
    edit_url?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    visibility: 'public' | 'request';
    visibility_label: string;
    status: 'active' | 'cancelled';
    status_label: string;
    active_attendees_count: number;
    max_attendees?: number | null;
    viewer_state: 'owner' | 'active' | 'pending';
};

const props = defineProps<{
    owned_events: MyEventSummary[];
    attending_events: MyEventSummary[];
    pending_events: MyEventSummary[];
}>();

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const locationLabel = (event: MyEventSummary) =>
    [event.postal_code, event.region].filter(Boolean).join(' ');

const attendeeLabel = (event: MyEventSummary) => {
    const base =
        event.active_attendees_count === 1
            ? '1 Teilnehmer'
            : `${event.active_attendees_count} Teilnehmer`;

    return event.max_attendees ? `${base} / max. ${event.max_attendees}` : base;
};

const stateLabel = (event: MyEventSummary) => {
    if (event.viewer_state === 'owner') {
        return 'Veranstalter';
    }

    if (event.viewer_state === 'pending') {
        return 'Anfrage gesendet';
    }

    return 'Teilnehmer';
};

const visibilityBadgeClass = (visibility: MyEventSummary['visibility']) =>
    visibility === 'request'
        ? 'border-primary/30 bg-primary/10 text-primary'
        : 'border-primary/30 bg-primary/10 text-primary';

const statusBadgeClass = (status: MyEventSummary['status']) =>
    status === 'cancelled'
        ? 'border-destructive/30 bg-destructive/10 text-destructive'
        : 'border-border bg-background/70 text-muted-foreground dark:bg-input/30';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Meine Events',
                href: '/my-events',
            },
        ],
    },
});
</script>

<template>
    <Head title="Meine Events" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <div
            class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <PageHeader
                title="Meine Events"
                description="Hier findest du deine erstellten Events, deine Teilnahmen und offene Teilnahme-Anfragen."
            />

            <div class="flex min-w-0 flex-col gap-2 sm:flex-row">
                <Button as-child class="w-full sm:w-auto">
                    <Link href="/events/create">Event erstellen</Link>
                </Button>
                <Button as-child variant="secondary" class="w-full sm:w-auto">
                    <Link href="/events">Events entdecken</Link>
                </Button>
            </div>
        </div>

        <PageSection>
            <div class="space-y-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold">Von mir erstellt</h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Deine eigenen Events, inklusive abgesagter Events.
                    </p>
                </div>

                <div
                    v-if="props.owned_events.length > 0"
                    class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <Card
                        v-for="event in props.owned_events"
                        :key="event.id"
                        class="h-full min-w-0 w-full border-border/80 bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                    >
                        <CardContent
                            class="flex h-full min-w-0 flex-col gap-4 p-5"
                        >
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <h3
                                        class="truncate text-lg font-semibold tracking-tight"
                                    >
                                        {{ event.title }}
                                    </h3>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ formatDateTime(event.starts_at) }}
                                    </p>
                                </div>

                                <Badge
                                    variant="outline"
                                    class="shrink-0"
                                    :class="statusBadgeClass(event.status)"
                                >
                                    {{ event.status_label }}
                                </Badge>
                            </div>

                            <div
                                class="grid min-w-0 gap-1 text-sm text-muted-foreground"
                            >
                                <p v-if="event.ends_at" class="truncate">
                                    Ende: {{ formatDateTime(event.ends_at) }}
                                </p>
                                <p
                                    v-if="event.region || event.postal_code"
                                    class="truncate"
                                >
                                    {{ locationLabel(event) }}
                                </p>
                                <p v-if="event.country_code" class="truncate">
                                    {{ event.country_code }}
                                </p>
                            </div>

                            <div class="flex min-w-0 flex-wrap gap-2">
                                <span
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ stateLabel(event) }}
                                </span>
                                <span
                                    class="max-w-full rounded-full px-3 py-1 text-xs font-medium break-words"
                                    :class="visibilityBadgeClass(event.visibility)"
                                >
                                    {{ event.visibility_label }}
                                </span>
                                <span
                                    v-if="event.category"
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ event.category.label }}
                                </span>
                                <span
                                    v-if="event.postal_code"
                                    class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                                >
                                    PLZ {{ event.postal_code }}
                                </span>
                                <span
                                    class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                                >
                                    {{ attendeeLabel(event) }}
                                </span>
                            </div>

                            <div class="mt-auto grid min-w-0 gap-2">
                                <Button
                                    as-child
                                    variant="secondary"
                                    class="max-w-full min-w-0 w-full"
                                >
                                    <Link :href="event.my_events_show_url">
                                        Event ansehen
                                    </Link>
                                </Button>
                                <Button
                                    v-if="event.edit_url"
                                    as-child
                                    class="max-w-full min-w-0 w-full"
                                >
                                    <Link :href="event.edit_url">
                                        Event bearbeiten
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card v-else>
                    <CardContent class="space-y-4 text-center sm:text-left">
                        <h3 class="text-base font-medium">
                            Du hast noch kein Event erstellt.
                        </h3>
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/events/create">Event erstellen</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection>
            <div class="space-y-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold">Meine Teilnahmen</h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Events, an denen du aktiv teilnimmst.
                    </p>
                </div>

                <div
                    v-if="props.attending_events.length > 0"
                    class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <Card
                        v-for="event in props.attending_events"
                        :key="event.id"
                        class="h-full min-w-0 w-full border-border/80 bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                    >
                        <CardContent
                            class="flex h-full min-w-0 flex-col gap-4 p-5"
                        >
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <h3
                                        class="truncate text-lg font-semibold tracking-tight"
                                    >
                                        {{ event.title }}
                                    </h3>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ formatDateTime(event.starts_at) }}
                                    </p>
                                </div>

                                <Badge
                                    variant="outline"
                                    class="shrink-0"
                                    :class="visibilityBadgeClass(event.visibility)"
                                >
                                    {{ event.visibility_label }}
                                </Badge>
                            </div>

                            <div
                                class="grid min-w-0 gap-1 text-sm text-muted-foreground"
                            >
                                <p v-if="event.ends_at" class="truncate">
                                    Ende: {{ formatDateTime(event.ends_at) }}
                                </p>
                                <p
                                    v-if="event.region || event.postal_code"
                                    class="truncate"
                                >
                                    {{ locationLabel(event) }}
                                </p>
                            </div>

                            <div class="flex min-w-0 flex-wrap gap-2">
                                <span
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ stateLabel(event) }}
                                </span>
                                <span
                                    v-if="event.category"
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ event.category.label }}
                                </span>
                                <span
                                    class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                                >
                                    {{ attendeeLabel(event) }}
                                </span>
                            </div>

                            <Button
                                as-child
                                variant="secondary"
                                class="mt-auto max-w-full min-w-0 w-full"
                            >
                                <Link :href="event.my_events_show_url">
                                    Event ansehen
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card v-else>
                    <CardContent class="space-y-4 text-center sm:text-left">
                        <h3 class="text-base font-medium">
                            Du nimmst aktuell an keinem Event teil.
                        </h3>
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/events">Events entdecken</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection>
            <div class="space-y-4">
                <div class="space-y-1">
                    <h2 class="text-base font-semibold">
                        Ausstehende Anfragen
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Teilnahme-Anfragen, die noch auf Bestätigung warten.
                    </p>
                </div>

                <div
                    v-if="props.pending_events.length > 0"
                    class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <Card
                        v-for="event in props.pending_events"
                        :key="event.id"
                        class="h-full min-w-0 w-full border-border/80 bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                    >
                        <CardContent
                            class="flex h-full min-w-0 flex-col gap-4 p-5"
                        >
                            <div
                                class="flex min-w-0 items-start justify-between gap-3"
                            >
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <h3
                                        class="truncate text-lg font-semibold tracking-tight"
                                    >
                                        {{ event.title }}
                                    </h3>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        {{ formatDateTime(event.starts_at) }}
                                    </p>
                                </div>

                                <Badge
                                    variant="outline"
                                    class="shrink-0"
                                    :class="visibilityBadgeClass(event.visibility)"
                                >
                                    {{ event.visibility_label }}
                                </Badge>
                            </div>

                            <div
                                class="grid min-w-0 gap-1 text-sm text-muted-foreground"
                            >
                                <p v-if="event.ends_at" class="truncate">
                                    Ende: {{ formatDateTime(event.ends_at) }}
                                </p>
                                <p
                                    v-if="event.region || event.postal_code"
                                    class="truncate"
                                >
                                    {{ locationLabel(event) }}
                                </p>
                            </div>

                            <div class="flex min-w-0 flex-wrap gap-2">
                                <span
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ stateLabel(event) }}
                                </span>
                                <span
                                    v-if="event.category"
                                    class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                                >
                                    {{ event.category.label }}
                                </span>
                                <span
                                    class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                                >
                                    {{ attendeeLabel(event) }}
                                </span>
                            </div>

                            <Button
                                as-child
                                variant="secondary"
                                class="mt-auto max-w-full min-w-0 w-full"
                            >
                                <Link :href="event.my_events_show_url">
                                    Event ansehen
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card v-else>
                    <CardContent class="space-y-4 text-center sm:text-left">
                        <h3 class="text-base font-medium">
                            Du hast derzeit keine offenen Teilnahme-Anfragen.
                        </h3>
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/events">Events entdecken</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
