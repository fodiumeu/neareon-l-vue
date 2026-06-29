<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    Compass,
    MessageCircle,
    Search,
    UserCog,
    UserRound,
    Users,
} from 'lucide-vue-next';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

type HomeUser = {
    name: string;
    username: string | null;
    region: string | null;
};

type OpenItem = {
    key: string;
    label: string;
    count: number;
    href: string;
};

type UpcomingEvent = {
    id: number;
    title: string;
    starts_at: string | null;
    region: string | null;
    href: string;
};

type HomeGroup = {
    id: number;
    name: string;
    region: string | null;
    category: string | null;
    members_count: number;
    href: string;
};

const page = usePage<{
    project: {
        dashboardTitle: string;
        dashboardDescription: string;
        hasStarterDefaults: boolean;
    };
    auth: {
        user: {
            role: string;
        } | null;
    };
    home: {
        user: HomeUser;
        openItems: OpenItem[];
        upcomingEvents: UpcomingEvent[];
        groups: HomeGroup[];
    };
}>();

const quickLinks = [
    {
        label: 'Mitglieder entdecken',
        description: 'Finde neue Profile in der Community.',
        href: '/discover?from=home',
        icon: UserRound,
    },
    {
        label: 'Gruppen entdecken',
        description: 'Stöbere durch offene Gruppen.',
        href: '/groups?from=home',
        icon: Users,
    },
    {
        label: 'Events entdecken',
        description: 'Sieh dir kommende Events an.',
        href: '/events?from=home',
        icon: CalendarDays,
    },
    {
        label: 'Meine Gruppen',
        description: 'Öffne deine Gruppenübersicht.',
        href: '/my-groups?from=home',
        icon: Users,
    },
    {
        label: 'Meine Events',
        description: 'Prüfe deine Events und Teilnahmen.',
        href: '/my-events?from=home',
        icon: CalendarDays,
    },
    {
        label: 'Nachrichten',
        description: 'Springe in deine Unterhaltungen.',
        href: '/messages?from=home',
        icon: MessageCircle,
    },
    {
        label: 'Profil bearbeiten',
        description: 'Aktualisiere deine sichtbaren Angaben.',
        href: '/profile/edit?from=home',
        icon: UserCog,
    },
];

const formatDateTime = (value: string | null) => {
    if (value === null) {
        return 'Termin offen';
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Home',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="page.props.project.dashboardTitle" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            :title="`Willkommen zurück, ${page.props.home.user.name}`"
            description="Dein kurzer Überblick über Community, Gruppen, Events und offene Punkte."
        />

        <PageSection>
            <Card
                class="overflow-hidden border-primary/25 bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/35"
            >
                <CardContent class="space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <p
                                v-if="page.props.home.user.region"
                                class="text-xs font-semibold tracking-[0.18em] text-primary uppercase"
                            >
                                {{ page.props.home.user.region }}
                            </p>
                            <h2 class="text-xl font-semibold tracking-tight">
                                Starte dort, wo gerade etwas passiert.
                            </h2>
                            <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                                Öffne deine wichtigsten Bereiche direkt oder
                                gehe über Entdecken zu Mitgliedern, Gruppen und
                                Events.
                            </p>
                        </div>
                        <Button as-child class="shrink-0">
                            <Link href="/explore">
                                <Compass class="size-4" aria-hidden="true" />
                                Entdecken öffnen
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                <Card>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-2">
                            <Search class="size-4 text-primary" aria-hidden="true" />
                            <h2 class="text-base font-medium">Schnellzugriff</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="item in quickLinks"
                                :key="item.label"
                                :href="item.href"
                                class="group flex min-h-24 min-w-0 gap-3 rounded-lg border border-border bg-background/60 p-4 transition-colors duration-200 hover:border-primary/45 hover:bg-primary/10"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full border border-border bg-accent text-accent-foreground"
                                >
                                    <component :is="item.icon" class="size-4" aria-hidden="true" />
                                </span>
                                <span class="min-w-0 space-y-1">
                                    <span class="block text-sm font-medium">
                                        {{ item.label }}
                                    </span>
                                    <span class="block text-sm leading-5 text-muted-foreground">
                                        {{ item.description }}
                                    </span>
                                </span>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-2">
                            <Bell class="size-4 text-primary" aria-hidden="true" />
                            <h2 class="text-base font-medium">Offene Punkte</h2>
                        </div>
                        <div
                            v-if="page.props.home.openItems.length > 0"
                            class="space-y-3"
                        >
                            <Link
                                v-for="item in page.props.home.openItems"
                                :key="item.key"
                                :href="item.href"
                                class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-border bg-background/60 p-4 transition-colors duration-200 hover:border-primary/45 hover:bg-primary/10"
                            >
                                <span class="min-w-0 text-sm font-medium">
                                    {{ item.label }}
                                </span>
                                <span
                                    class="flex min-w-8 shrink-0 items-center justify-center rounded-full bg-primary px-2 py-1 text-xs font-semibold text-primary-foreground"
                                >
                                    {{ item.count }}
                                </span>
                            </Link>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-border bg-background/50 p-4 text-sm text-muted-foreground"
                        >
                            Aktuell ist nichts offen.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <CalendarDays class="size-4 text-primary" aria-hidden="true" />
                                <h2 class="text-base font-medium">Meine nächsten Events</h2>
                            </div>
                            <Button as-child variant="secondary" size="sm">
                                <Link href="/my-events?from=home">Alle ansehen</Link>
                            </Button>
                        </div>
                        <div
                            v-if="page.props.home.upcomingEvents.length > 0"
                            class="space-y-3"
                        >
                            <Link
                                v-for="event in page.props.home.upcomingEvents"
                                :key="event.id"
                                :href="event.href"
                                class="block min-w-0 rounded-lg border border-border bg-background/60 p-4 transition-colors duration-200 hover:border-primary/45 hover:bg-primary/10"
                            >
                                <span class="block truncate text-sm font-medium">
                                    {{ event.title }}
                                </span>
                                <span class="mt-2 block text-sm text-muted-foreground">
                                    {{ formatDateTime(event.starts_at) }}
                                    <template v-if="event.region">
                                        · {{ event.region }}
                                    </template>
                                </span>
                            </Link>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-border bg-background/50 p-4 text-sm text-muted-foreground"
                        >
                            Du hast aktuell keine anstehenden Events.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <Users class="size-4 text-primary" aria-hidden="true" />
                                <h2 class="text-base font-medium">Meine Gruppen</h2>
                            </div>
                            <Button as-child variant="secondary" size="sm">
                                <Link href="/my-groups?from=home">Alle ansehen</Link>
                            </Button>
                        </div>
                        <div
                            v-if="page.props.home.groups.length > 0"
                            class="space-y-3"
                        >
                            <Link
                                v-for="group in page.props.home.groups"
                                :key="group.id"
                                :href="group.href"
                                class="block min-w-0 rounded-lg border border-border bg-background/60 p-4 transition-colors duration-200 hover:border-primary/45 hover:bg-primary/10"
                            >
                                <span class="block truncate text-sm font-medium">
                                    {{ group.name }}
                                </span>
                                <span class="mt-2 block text-sm text-muted-foreground">
                                    <template v-if="group.category">
                                        {{ group.category }}
                                    </template>
                                    <template v-else>Gruppe</template>
                                    <template v-if="group.region">
                                        · {{ group.region }}
                                    </template>
                                    · {{ group.members_count }} Mitglieder
                                </span>
                            </Link>
                        </div>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-border bg-background/50 p-4 text-sm text-muted-foreground"
                        >
                            Du bist aktuell in keiner Gruppe.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection v-if="page.props.project.hasStarterDefaults">
            <Card class="border-primary/30 bg-primary/10 shadow-primary/5">
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">NEAREON-Basis aktiv</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Die zentralen Projektwerte zeigen auf NEAREON. Weitere
                        Schritte bleiben bewusst klein und nachvollziehbar.
                    </p>
                    <p
                        v-if="page.props.auth.user?.role === 'admin'"
                        class="text-sm text-muted-foreground"
                    >
                        Prüfe die aktuellen Projektwerte im
                        <Link
                            href="/admin/system"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                        >
                            Systemstatus
                        </Link>
                        .
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
