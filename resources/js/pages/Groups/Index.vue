<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

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

type GroupDiscoverFilters = {
    q: string;
    region: string;
    category: string;
    visibility: string;
};

type GroupCategoryOption = {
    id: number;
    slug: string;
    label: string;
};

type GroupVisibilityOption = {
    value: string;
    label: string;
};

type GroupFilterOptions = {
    regions: string[];
    categories: GroupCategoryOption[];
    visibilities: GroupVisibilityOption[];
};

const props = defineProps<{
    groups: PaginatedGroups;
    filters: GroupDiscoverFilters;
    filterOptions: GroupFilterOptions;
}>();

const allFilterValue = '__all__';
const searchQuery = ref(props.filters.q);
const selectedRegion = ref(props.filters.region);
const selectedCategory = ref(props.filters.category);
const selectedVisibility = ref(props.filters.visibility);
const filtersOpen = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const groupSelectTriggerClass =
    'w-full border-input bg-background text-foreground hover:border-ring/70 hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border/90 dark:bg-input/35 dark:hover:bg-accent/45';
const groupSelectContentClass =
    'border-border bg-popover text-popover-foreground shadow-xl shadow-black/30';
const groupSelectItemClass =
    'focus:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] focus:text-popover-foreground data-[highlighted]:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] data-[highlighted]:text-popover-foreground data-[state=checked]:bg-action-primary data-[state=checked]:text-action-primary-foreground data-[state=checked]:focus:bg-action-primary data-[state=checked]:focus:text-action-primary-foreground';

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

const toSelectValue = (value: string) => value || allFilterValue;

const fromSelectValue = (value: string | number | boolean | null | undefined) =>
    typeof value === 'string' && value !== allFilterValue ? value : '';

const clearSearchTimer = () => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }
};

const runSearch = () => {
    clearSearchTimer();

    const query = searchQuery.value.trim();
    const params: Record<string, string> = {};

    if (query) {
        params.q = query;
    }

    if (selectedRegion.value) {
        params.region = selectedRegion.value;
    }

    if (selectedCategory.value) {
        params.category = selectedCategory.value;
    }

    if (selectedVisibility.value) {
        params.visibility = selectedVisibility.value;
    }

    if (
        query === props.filters.q &&
        selectedRegion.value === props.filters.region &&
        selectedCategory.value === props.filters.category &&
        selectedVisibility.value === props.filters.visibility
    ) {
        return;
    }

    router.get('/groups', params, {
        only: ['groups', 'filters', 'filterOptions'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ['groups'],
    });
};

const handleSearchInput = () => {
    clearSearchTimer();

    if (searchQuery.value.trim() === '') {
        runSearch();

        return;
    }

    searchTimer = setTimeout(runSearch, 350);
};

const applyFilters = () => {
    clearSearchTimer();
    runSearch();
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedRegion.value = '';
    selectedCategory.value = '';
    selectedVisibility.value = '';
    applyFilters();
};

const selectedRegionOption = computed({
    get: () => toSelectValue(selectedRegion.value),
    set: (value) => {
        selectedRegion.value = fromSelectValue(value);
        applyFilters();
    },
});

const selectedCategoryOption = computed({
    get: () => toSelectValue(selectedCategory.value),
    set: (value) => {
        selectedCategory.value = fromSelectValue(value);
        applyFilters();
    },
});

const selectedVisibilityOption = computed({
    get: () => toSelectValue(selectedVisibility.value),
    set: (value) => {
        selectedVisibility.value = fromSelectValue(value);
        applyFilters();
    },
});

const hasActiveFilters = computed(
    () =>
        searchQuery.value.trim() !== '' ||
        selectedRegion.value !== '' ||
        selectedCategory.value !== '' ||
        selectedVisibility.value !== '',
);

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters.q;
        selectedRegion.value = filters.region;
        selectedCategory.value = filters.category;
        selectedVisibility.value = filters.visibility;
    },
);

onBeforeUnmount(clearSearchTimer);

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
        <Button
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link href="/explore" class="min-w-0 truncate">
                ← Zurück zu Entdecken
            </Link>
        </Button>

        <PageHeader
            title="Gruppen entdecken"
            description="Entdecke öffentliche und offene Gruppen aus der NEAREON-Community."
        />

        <PageSection>
            <form
                action="/groups"
                method="get"
                class="group-filter-controls"
                @submit.prevent="applyFilters"
            >
                <Card
                    class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
                >
                    <CardContent class="space-y-4">
                        <div class="grid gap-4">
                            <div class="grid max-w-xl gap-2">
                                <Label for="group-search">
                                    Gruppen durchsuchen
                                </Label>
                                <div class="relative">
                                    <Input
                                        id="group-search"
                                        v-model="searchQuery"
                                        name="q"
                                        type="search"
                                        placeholder="Name, Beschreibung, Region oder PLZ"
                                        autocomplete="off"
                                        class="pr-11"
                                        @input="handleSearchInput"
                                    />
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="icon"
                                        class="absolute top-1/2 right-1 size-8 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                        aria-label="Gruppen suchen"
                                    >
                                        <Search
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="secondary"
                                class="w-full justify-between md:hidden"
                                :aria-expanded="filtersOpen"
                                aria-controls="group-filters"
                                @click="filtersOpen = !filtersOpen"
                            >
                                {{
                                    filtersOpen
                                        ? 'Filter ausblenden'
                                        : 'Filter anzeigen'
                                }}
                                <span aria-hidden="true">
                                    {{ filtersOpen ? '▲' : '▼' }}
                                </span>
                            </Button>

                            <div
                                id="group-filters"
                                class="gap-3 md:grid-cols-4 md:items-end"
                                :class="
                                    filtersOpen
                                        ? 'grid md:grid'
                                        : 'hidden md:grid'
                                "
                            >
                                <div class="grid gap-2">
                                    <Label for="group-region">Region</Label>
                                    <input
                                        type="hidden"
                                        name="region"
                                        :value="selectedRegion"
                                    />
                                    <Select v-model="selectedRegionOption">
                                        <SelectTrigger
                                            id="group-region"
                                            :class="groupSelectTriggerClass"
                                            aria-label="Region auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle Regionen"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="groupSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="groupSelectItemClass"
                                            >
                                                Alle Regionen
                                            </SelectItem>
                                            <SelectItem
                                                v-for="region in filterOptions.regions"
                                                :key="region"
                                                :value="region"
                                                :class="groupSelectItemClass"
                                            >
                                                {{ region }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="group-category">
                                        Kategorie
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="category"
                                        :value="selectedCategory"
                                    />
                                    <Select v-model="selectedCategoryOption">
                                        <SelectTrigger
                                            id="group-category"
                                            :class="groupSelectTriggerClass"
                                            aria-label="Kategorie auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle Kategorien"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="groupSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="groupSelectItemClass"
                                            >
                                                Alle Kategorien
                                            </SelectItem>
                                            <SelectItem
                                                v-for="category in filterOptions.categories"
                                                :key="category.slug"
                                                :value="category.slug"
                                                :class="groupSelectItemClass"
                                            >
                                                {{ category.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="group-visibility">
                                        Sichtbarkeit
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="visibility"
                                        :value="selectedVisibility"
                                    />
                                    <Select v-model="selectedVisibilityOption">
                                        <SelectTrigger
                                            id="group-visibility"
                                            :class="groupSelectTriggerClass"
                                            aria-label="Sichtbarkeit auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle sichtbaren Gruppen"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="groupSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="groupSelectItemClass"
                                            >
                                                Alle sichtbaren Gruppen
                                            </SelectItem>
                                            <SelectItem
                                                v-for="visibility in filterOptions.visibilities"
                                                :key="visibility.value"
                                                :value="visibility.value"
                                                :class="groupSelectItemClass"
                                            >
                                                {{ visibility.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <Button
                                    type="button"
                                    variant="secondary"
                                    class="w-full"
                                    :disabled="!hasActiveFilters"
                                    @click="resetFilters"
                                >
                                    Filter zurücksetzen
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </PageSection>

        <PageSection v-if="groups.data.length === 0">
            <Card>
                <CardContent class="space-y-4 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        {{
                            hasActiveFilters
                                ? 'Keine passenden Gruppen gefunden'
                                : 'Noch keine Gruppen zum Entdecken sichtbar.'
                        }}
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{
                            hasActiveFilters
                                ? 'Passe deine Suche oder Filter an. Du kannst auch wieder alle sichtbaren Gruppen anzeigen.'
                                : 'Sobald öffentliche oder offene Gruppen verfügbar sind, erscheinen sie hier.'
                        }}
                    </p>
                    <div
                        v-if="hasActiveFilters"
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <Button
                            type="button"
                            variant="secondary"
                            class="w-full sm:w-auto"
                            @click="resetFilters"
                        >
                            Filter zurücksetzen
                        </Button>
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/groups">Alle Gruppen anzeigen</Link>
                        </Button>
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

                        <div class="mt-auto grid min-w-0 gap-2">
                            <Form
                                v-if="group.can_join && group.join_url"
                                :action="group.join_url"
                                method="post"
                                class="min-w-0 w-full"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="return_to"
                                    value="groups"
                                />
                                <Button
                                    type="submit"
                                    class="max-w-full min-w-0 w-full"
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

                            <Button
                                as-child
                                variant="secondary"
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
