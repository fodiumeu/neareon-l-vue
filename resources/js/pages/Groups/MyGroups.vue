<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CommunityBackLink from '@/components/CommunityBackLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type GroupSummary = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    visibility: 'public' | 'request' | 'private';
    visibility_label: string;
    member_count: number;
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    membership?: {
        role_label: string;
        status: string;
        status_label: string;
    } | null;
    url: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedGroups = {
    data: GroupSummary[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

type BackLink = {
    href: string;
    label: string;
    source: 'home' | null;
};

defineProps<{
    backLink: BackLink;
    groups: PaginatedGroups;
}>();

const visibilityBadgeClass = (visibility: GroupSummary['visibility']) =>
    visibility === 'private'
        ? 'border-border bg-background/70 text-muted-foreground dark:bg-input/30'
        : visibility === 'request'
          ? 'border-primary/30 bg-primary/10 text-primary'
          : 'border-primary/30 bg-primary/10 text-primary';

const shortDescription = (description?: string | null) => {
    if (!description) {
        return 'Diese Gruppe hat noch keine Beschreibung.';
    }

    return description.length > 160
        ? `${description.slice(0, 157).trim()}...`
        : description;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Meine Gruppen',
                href: '/my-groups',
            },
        ],
    },
});
</script>

<template>
    <Head title="Meine Gruppen" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <CommunityBackLink :href="backLink.href" :label="backLink.label" />

        <PageHeader
            title="Meine Gruppen"
            description="Hier findest du Gruppen, denen du angehörst oder zu denen du eingeladen wurdest."
        />

        <PageSection v-if="groups.data.length === 0">
            <Card>
                <CardContent class="space-y-4 text-center sm:text-left">
                    <div class="space-y-2">
                        <h2 class="text-base font-medium">
                            Du bist noch in keiner Gruppe.
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Sobald du Gruppen erstellst oder Gruppen
                            beitrittst, findest du sie hier.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="group in groups.data"
                    :key="group.id"
                    class="h-full min-w-0 w-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full min-w-0 flex-col gap-4 p-5">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <h2
                                    class="truncate text-lg font-semibold tracking-tight"
                                >
                                    {{ group.name }}
                                </h2>
                                <p
                                    v-if="group.region || group.postal_code"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{
                                        [group.postal_code, group.region]
                                            .filter(Boolean)
                                            .join(' ')
                                    }}
                                </p>
                            </div>

                            <Badge
                                variant="outline"
                                class="shrink-0"
                                :class="visibilityBadgeClass(group.visibility)"
                            >
                                {{ group.visibility_label }}
                            </Badge>
                        </div>

                        <p class="min-w-0 text-sm leading-6 break-words text-muted-foreground">
                            {{ shortDescription(group.description) }}
                        </p>

                        <div class="flex min-w-0 flex-wrap gap-2">
                            <span
                                v-if="group.category"
                                class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                            >
                                {{ group.category.label }}
                            </span>
                            <span
                                v-if="group.region"
                                class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                            >
                                {{ group.region }}
                            </span>
                            <span
                                v-if="group.postal_code"
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                PLZ {{ group.postal_code }}
                            </span>
                            <span
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                {{ group.member_count }}
                                {{
                                    group.member_count === 1
                                        ? 'Mitglied'
                                        : 'Mitglieder'
                                }}
                            </span>
                            <span
                                v-if="group.membership"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                {{
                                    group.membership.status === 'pending'
                                        ? group.membership.status_label
                                        : group.membership.role_label
                                }}
                            </span>
                        </div>

                        <div class="mt-auto grid min-w-0 gap-2">
                            <Button
                                as-child
                                class="max-w-full min-w-0 w-full"
                            >
                                <Link :href="group.url">Gruppe ansehen</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="groups.last_page > 1"
                class="mt-6 flex items-center justify-center gap-2"
                aria-label="Meine Gruppen Seiten"
            >
                <Button
                    as-child
                    variant="secondary"
                    :disabled="!groups.prev_page_url"
                >
                    <Link v-if="groups.prev_page_url" :href="groups.prev_page_url">
                        ← Vorherige
                    </Link>
                    <span v-else>← Vorherige</span>
                </Button>

                <Button
                    as-child
                    variant="secondary"
                    :disabled="!groups.next_page_url"
                >
                    <Link v-if="groups.next_page_url" :href="groups.next_page_url">
                        Nächste →
                    </Link>
                    <span v-else>Nächste →</span>
                </Button>
            </nav>
        </PageSection>

        <PageSection>
            <Card class="min-w-0 w-full">
                <CardContent class="min-w-0 space-y-4 text-center sm:text-left">
                    <div class="min-w-0 space-y-2">
                        <h2 class="text-base font-medium">
                            {{
                                groups.data.length === 0
                                    ? 'Starte deine Community'
                                    : 'Weitere Gruppen'
                            }}
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{
                                groups.data.length === 0
                                    ? 'Erstelle eine eigene Gruppe oder entdecke passende Gruppen aus der NEAREON-Community.'
                                    : 'Erstelle eine weitere Gruppe oder entdecke neue Communities.'
                            }}
                        </p>
                    </div>
                    <div class="flex min-w-0 flex-col gap-2 sm:flex-row">
                        <Button as-child class="max-w-full min-w-0 w-full sm:w-auto">
                            <Link href="/groups/create">Gruppe erstellen</Link>
                        </Button>
                        <Button
                            as-child
                            variant="secondary"
                            class="max-w-full min-w-0 w-full sm:w-auto"
                        >
                            <Link href="/groups">Gruppen entdecken</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
