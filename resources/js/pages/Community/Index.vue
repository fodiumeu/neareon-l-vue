<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleSlash2,
    ContactRound,
    Inbox,
    LayoutGrid,
    Send,
    Users,
} from 'lucide-vue-next';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type CommunityCard = {
    title: string;
    description: string;
    href: string;
    actionLabel: string;
    icon: typeof ContactRound;
    count?: number;
    countLabel?: string;
};

const props = defineProps<{
    communityCounts: {
        pendingContactRequests: number;
    };
}>();

const communityCards: CommunityCard[] = [
    {
        title: 'Meine Gruppen',
        description:
            'Gruppen, denen du angehörst oder zu denen du eingeladen wurdest.',
        href: '/my-groups',
        actionLabel: 'Meine Gruppen öffnen',
        icon: Users,
    },
    {
        title: 'Kontakte',
        description:
            'Mitglieder, denen du folgst und die dir ebenfalls folgen.',
        href: '/contacts',
        actionLabel: 'Kontakte öffnen',
        icon: ContactRound,
    },
    {
        title: 'Follower',
        description: 'Mitglieder, die dir folgen.',
        href: '/followers',
        actionLabel: 'Follower anzeigen',
        icon: Users,
    },
    {
        title: 'Ich folge',
        description: 'Profile, denen du folgst.',
        href: '/following',
        actionLabel: 'Following öffnen',
        icon: Users,
    },
    {
        title: 'Kontaktanfragen',
        description: 'Offene Anfragen, die auf deine Antwort warten.',
        href: '/contact-requests',
        actionLabel: 'Anfragen ansehen',
        icon: Inbox,
        count: props.communityCounts.pendingContactRequests,
        countLabel: 'offen',
    },
    {
        title: 'Gesendete Anfragen',
        description: 'Anfragen, die du an andere Mitglieder gesendet hast.',
        href: '/contact-requests/sent',
        actionLabel: 'Gesendete Anfragen öffnen',
        icon: Send,
    },
    {
        title: 'Blockierte Profile',
        description: 'Profile, die du blockiert hast.',
        href: '/blocked-profiles',
        actionLabel: 'Blockierte Profile öffnen',
        icon: CircleSlash2,
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Community',
                href: '/community',
            },
        ],
    },
});
</script>

<template>
    <Head title="Community" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            title="Community"
            description="Dein zentraler Einstieg zu Kontakten, Followern, Anfragen und blockierten Profilen."
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:border-primary/15 dark:shadow-black/35"
            >
                <CardContent class="relative space-y-4">
                    <div
                        class="pointer-events-none absolute -top-24 -right-20 size-56 rounded-full bg-action-primary/12 blur-3xl"
                        aria-hidden="true"
                    />
                    <div class="relative flex items-start gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-primary/25 bg-primary/10 text-primary"
                        >
                            <LayoutGrid class="size-5" aria-hidden="true" />
                        </div>
                        <div class="min-w-0 space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-primary uppercase"
                            >
                                Übersicht
                            </p>
                            <h2
                                class="text-xl font-semibold tracking-tight sm:text-2xl"
                            >
                                Alles rund um dein Netzwerk an einem Ort.
                            </h2>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                Springe direkt in die bestehenden
                                Community-Bereiche, ohne dich durch einzelne
                                Menüpunkte zu hangeln.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="card in communityCards"
                    :key="card.href"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-border bg-background/70 text-primary dark:bg-input/25"
                            >
                                <component
                                    :is="card.icon"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <Badge
                                v-if="typeof card.count === 'number'"
                                variant="secondary"
                                class="border-primary/30 bg-primary/10 text-primary"
                            >
                                {{ card.count }} {{ card.countLabel }}
                            </Badge>
                        </div>

                        <div class="min-w-0 flex-1 space-y-2">
                            <h2 class="text-base font-semibold">
                                {{ card.title }}
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ card.description }}
                            </p>
                        </div>

                        <Button as-child class="w-full">
                            <Link :href="card.href">
                                {{ card.actionLabel }}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
