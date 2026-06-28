<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

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

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Event',
                href: '/events/create',
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
            <Link href="/dashboard" class="min-w-0 truncate">← Zurück</Link>
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
                <CardContent class="p-5">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Teilnahmefunktionen folgen in einem späteren Modul.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
