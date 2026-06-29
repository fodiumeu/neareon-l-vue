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

type EventSummary = {
    id: number;
    title: string;
    slug: string;
    description?: string | null;
    show_url: string;
    starts_at?: string | null;
    ends_at?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    visibility: 'public' | 'request';
    visibility_label: string;
    status: 'active';
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    attendee_count: number;
    max_attendees?: number | null;
    owner: {
        name: string;
        username?: string | null;
    };
    is_full: boolean;
    viewer_attendance_status: 'active' | 'pending' | null;
    viewer_event_role: 'owner' | 'attendee' | 'pending' | 'none';
    can_join: boolean;
    can_request: boolean;
    can_leave: boolean;
    attendance_store_url?: string | null;
    attendance_destroy_url?: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEvents = {
    data: EventSummary[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

type EventDiscoverFilters = {
    q: string;
    region: string;
    category: string;
    visibility: string;
};

type EventCategoryOption = {
    id: number;
    slug: string;
    label: string;
};

type EventVisibilityOption = {
    value: string;
    label: string;
};

type EventFilterOptions = {
    regions: string[];
    categories: EventCategoryOption[];
    visibilities: EventVisibilityOption[];
};

type BackLink = {
    href: string;
    label: string;
    source: 'home' | 'explore' | null;
};

const props = defineProps<{
    backLink: BackLink;
    events: PaginatedEvents;
    filters: EventDiscoverFilters;
    filterOptions: EventFilterOptions;
}>();

const allFilterValue = '__all__';
const searchQuery = ref(props.filters.q);
const selectedRegion = ref(props.filters.region);
const selectedCategory = ref(props.filters.category);
const selectedVisibility = ref(props.filters.visibility);
const filtersOpen = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const eventSelectTriggerClass =
    'w-full border-input bg-background text-foreground hover:border-ring/70 hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border/90 dark:bg-input/35 dark:hover:bg-accent/45';
const eventSelectContentClass =
    'border-border bg-popover text-popover-foreground shadow-xl shadow-black/30';
const eventSelectItemClass =
    'focus:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] focus:text-popover-foreground data-[highlighted]:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] data-[highlighted]:text-popover-foreground data-[state=checked]:bg-action-primary data-[state=checked]:text-action-primary-foreground data-[state=checked]:focus:bg-action-primary data-[state=checked]:focus:text-action-primary-foreground';

const visibilityBadgeClass = (visibility: EventSummary['visibility']) =>
    visibility === 'request'
        ? 'border-primary/30 bg-primary/10 text-primary'
        : 'border-primary/30 bg-primary/10 text-primary';

const fallbackDescription = (description?: string | null) =>
    description || 'Dieses Event hat noch keine Beschreibung.';

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const locationLabel = (event: EventSummary) =>
    [event.postal_code, event.region].filter(Boolean).join(' ');

const attendeeLabel = (event: EventSummary) => {
    const base =
        event.attendee_count === 1
            ? '1 Teilnehmer'
            : `${event.attendee_count} Teilnehmer`;

    return event.max_attendees ? `${base} / max. ${event.max_attendees}` : base;
};

const statusBadgeLabel = (event: EventSummary) => {
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

const statusHint = (event: EventSummary) => {
    if (event.viewer_attendance_status === 'active') {
        return 'Du nimmst an diesem Event teil.';
    }

    if (event.viewer_attendance_status === 'pending') {
        return 'Deine Teilnahme-Anfrage wartet auf Bestätigung.';
    }

    return null;
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

    if (props.backLink.source) {
        params.from = props.backLink.source;
    }

    if (
        query === props.filters.q &&
        selectedRegion.value === props.filters.region &&
        selectedCategory.value === props.filters.category &&
        selectedVisibility.value === props.filters.visibility
    ) {
        return;
    }

    router.get('/events', params, {
        only: ['events', 'filters', 'filterOptions'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ['events'],
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
                title: 'Events',
                href: '/events',
            },
        ],
    },
});
</script>

<template>
    <Head title="Events entdecken" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <Button
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link :href="backLink.href" class="min-w-0 truncate">
                ← {{ backLink.label }}
            </Link>
        </Button>

        <div
            class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <PageHeader
                title="Events entdecken"
                description="Entdecke regionale Events aus der NEAREON-Community."
            />

            <Button as-child class="w-full sm:w-auto">
                <Link href="/events/create">Event erstellen</Link>
            </Button>
        </div>

        <PageSection>
            <form
                action="/events"
                method="get"
                class="event-filter-controls"
                @submit.prevent="applyFilters"
            >
                <Card
                    class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
                >
                    <CardContent class="space-y-4">
                        <div class="grid gap-4">
                            <div class="grid max-w-xl gap-2">
                                <Label for="event-search">
                                    Events durchsuchen
                                </Label>
                                <div class="relative">
                                    <Input
                                        id="event-search"
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
                                        aria-label="Events suchen"
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
                                aria-controls="event-filters"
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
                                id="event-filters"
                                class="gap-3 md:grid-cols-4 md:items-end"
                                :class="
                                    filtersOpen
                                        ? 'grid md:grid'
                                        : 'hidden md:grid'
                                "
                            >
                                <div class="grid gap-2">
                                    <Label for="event-region">Region</Label>
                                    <input
                                        type="hidden"
                                        name="region"
                                        :value="selectedRegion"
                                    />
                                    <Select v-model="selectedRegionOption">
                                        <SelectTrigger
                                            id="event-region"
                                            :class="eventSelectTriggerClass"
                                            aria-label="Region auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle Regionen"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="eventSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="eventSelectItemClass"
                                            >
                                                Alle Regionen
                                            </SelectItem>
                                            <SelectItem
                                                v-for="region in filterOptions.regions"
                                                :key="region"
                                                :value="region"
                                                :class="eventSelectItemClass"
                                            >
                                                {{ region }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="event-category">
                                        Kategorie
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="category"
                                        :value="selectedCategory"
                                    />
                                    <Select v-model="selectedCategoryOption">
                                        <SelectTrigger
                                            id="event-category"
                                            :class="eventSelectTriggerClass"
                                            aria-label="Kategorie auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle Kategorien"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="eventSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="eventSelectItemClass"
                                            >
                                                Alle Kategorien
                                            </SelectItem>
                                            <SelectItem
                                                v-for="category in filterOptions.categories"
                                                :key="category.slug"
                                                :value="category.slug"
                                                :class="eventSelectItemClass"
                                            >
                                                {{ category.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="event-visibility">
                                        Sichtbarkeit
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="visibility"
                                        :value="selectedVisibility"
                                    />
                                    <Select v-model="selectedVisibilityOption">
                                        <SelectTrigger
                                            id="event-visibility"
                                            :class="eventSelectTriggerClass"
                                            aria-label="Sichtbarkeit auswählen"
                                        >
                                            <SelectValue
                                                placeholder="Alle sichtbaren Events"
                                            />
                                        </SelectTrigger>
                                        <SelectContent
                                            :class="eventSelectContentClass"
                                        >
                                            <SelectItem
                                                :value="allFilterValue"
                                                :class="eventSelectItemClass"
                                            >
                                                Alle sichtbaren Events
                                            </SelectItem>
                                            <SelectItem
                                                v-for="visibility in filterOptions.visibilities"
                                                :key="visibility.value"
                                                :value="visibility.value"
                                                :class="eventSelectItemClass"
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

        <PageSection v-if="events.data.length === 0">
            <Card>
                <CardContent class="space-y-4 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        {{
                            hasActiveFilters
                                ? 'Keine passenden Events gefunden'
                                : 'Noch keine Events sichtbar.'
                        }}
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{
                            hasActiveFilters
                                ? 'Passe deine Suche oder Filter an.'
                                : 'Erstelle das erste regionale Event für deine Community.'
                        }}
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="secondary"
                            class="w-full sm:w-auto"
                            @click="resetFilters"
                        >
                            Filter zurücksetzen
                        </Button>
                        <Button
                            v-if="hasActiveFilters"
                            as-child
                            class="w-full sm:w-auto"
                        >
                            <Link href="/events">Alle Events anzeigen</Link>
                        </Button>
                        <Button v-else as-child class="w-full sm:w-auto">
                            <Link href="/events/create">Event erstellen</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="event in events.data"
                    :key="event.id"
                    class="h-full min-w-0 w-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full min-w-0 flex-col gap-4 p-5">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <h2
                                    class="truncate text-lg font-semibold tracking-tight"
                                >
                                    {{ event.title }}
                                </h2>
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

                        <p class="min-w-0 text-sm leading-6 break-words text-muted-foreground">
                            {{ fallbackDescription(event.description) }}
                        </p>

                        <div class="flex min-w-0 flex-wrap gap-2">
                            <span
                                v-if="event.category"
                                class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                            >
                                {{ event.category.label }}
                            </span>
                            <span
                                v-if="event.region"
                                class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                            >
                                {{ event.region }}
                            </span>
                            <span
                                v-if="event.postal_code"
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                PLZ {{ event.postal_code }}
                            </span>
                            <span
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                {{ attendeeLabel(event) }}
                            </span>
                            <span
                                v-if="event.owner.name"
                                class="max-w-full rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium break-words text-muted-foreground dark:bg-input/30"
                            >
                                Von {{ event.owner.name }}
                            </span>
                            <span
                                v-if="statusBadgeLabel(event)"
                                class="max-w-full rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium break-words text-primary"
                            >
                                {{ statusBadgeLabel(event) }}
                            </span>
                        </div>

                        <div class="mt-auto grid min-w-0 gap-2">
                            <p
                                v-if="statusHint(event)"
                                class="rounded-md border border-primary/20 bg-primary/10 px-3 py-2 text-sm text-primary"
                            >
                                {{ statusHint(event) }}
                            </p>

                            <p
                                v-else-if="event.is_full && event.viewer_event_role === 'none'"
                                class="rounded-md border border-border bg-background/70 px-3 py-2 text-sm text-muted-foreground dark:bg-input/30"
                            >
                                Dieses Event ist bereits ausgebucht.
                            </p>

                            <Form
                                v-if="(event.can_join || event.can_request) && event.attendance_store_url"
                                :action="event.attendance_store_url"
                                method="post"
                                class="min-w-0 w-full"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    class="max-w-full min-w-0 w-full"
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

                            <Button
                                as-child
                                variant="secondary"
                                class="max-w-full min-w-0 w-full"
                            >
                                <Link :href="event.show_url">
                                    Event ansehen
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="events.last_page > 1"
                class="mt-6 flex items-center justify-center gap-2"
                aria-label="Event Seiten"
            >
                <Button
                    as-child
                    variant="secondary"
                    :disabled="!events.prev_page_url"
                >
                    <Link v-if="events.prev_page_url" :href="events.prev_page_url">
                        ← Vorherige
                    </Link>
                    <span v-else>← Vorherige</span>
                </Button>

                <Button
                    as-child
                    variant="secondary"
                    :disabled="!events.next_page_url"
                >
                    <Link v-if="events.next_page_url" :href="events.next_page_url">
                        Nächste →
                    </Link>
                    <span v-else>Nächste →</span>
                </Button>
            </nav>
        </PageSection>
    </div>
</template>
