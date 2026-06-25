<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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
    can_join: boolean;
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    join_label?: string | null;
    join_url?: string | null;
    membership?: {
        role_label: string;
        status: string;
        status_label: string;
    } | null;
    url: string;
    viewer_membership_status?: string | null;
    viewer_role?: string | null;
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

defineProps<{
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
                title: 'Gruppen',
                href: '/groups',
            },
        ],
    },
});
</script>

<template>
    <Head title="Gruppen entdecken" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            title="Gruppen entdecken"
            description="Entdecke öffentliche und offene Gruppen aus der NEAREON-Community."
        />

        <PageSection v-if="groups.data.length === 0">
            <Card>
                <CardContent class="space-y-2 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        Noch keine Gruppen zum Entdecken sichtbar.
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Sobald öffentliche Gruppen verfügbar sind, erscheinen
                        sie hier.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="group in groups.data"
                    :key="group.id"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1.5">
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
                                :class="visibilityBadgeClass(group.visibility)"
                            >
                                {{ group.visibility_label }}
                            </Badge>
                        </div>

                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ shortDescription(group.description) }}
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="group.category"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                {{ group.category.label }}
                            </span>
                            <span
                                v-if="group.region"
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
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
                                v-if="group.viewer_membership_status === 'pending'"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                Anfrage gesendet
                            </span>
                            <span
                                v-else-if="group.viewer_membership_status === 'active' && group.viewer_role"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                {{ group.membership?.role_label ?? 'Mitglied' }}
                            </span>
                        </div>

                        <div class="mt-auto grid gap-2">
                            <Form
                                v-if="group.can_join && group.join_url"
                                :action="group.join_url"
                                method="post"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value="groups"
                                />
                                <Button
                                    type="submit"
                                    class="w-full"
                                    :disabled="processing"
                                >
                                    {{
                                        processing
                                            ? 'Wird verarbeitet...'
                                            : group.join_label
                                    }}
                                </Button>
                            </Form>
                            <p
                                v-else-if="group.viewer_membership_status === 'pending'"
                                class="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm text-primary"
                            >
                                Deine Beitrittsanfrage wartet auf Bestätigung.
                            </p>

                            <Button as-child variant="secondary" class="w-full">
                                <Link :href="group.url">Gruppe ansehen</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="groups.last_page > 1"
                class="mt-6 flex items-center justify-center gap-2"
                aria-label="Gruppen Seiten"
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
    </div>
</template>
