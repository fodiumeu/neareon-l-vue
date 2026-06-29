<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ContactActions from '@/components/ContactActions.vue';
import ContactStatusBadge from '@/components/ContactStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { profileUrl } from '@/lib/relationshipActions';
import type { ContactStatus } from '@/types';

type DiscoverProfile = {
    can_follow: boolean;
    can_send_contact_request: boolean;
    contact_user_id: number;
    contact_request_unavailable_reason?: 'disabled' | 'follow_required' | null;
    incoming_contact_request_id?: number | null;
    interaction_blocked: boolean;
    is_blocked_by_viewer: boolean;
    username: string;
    isOwnProfile: boolean;
    is_following: boolean;
    is_followed_by: boolean;
    is_mutual: boolean;
    contact_status: ContactStatus;
    profile_photo_url?: string | null;
    display_name?: string;
    bio?: string | null;
    region?: string | null;
    languages?: string[] | null;
    interests?: string[] | null;
    common_languages?: string[];
    common_interests?: string[];
};

type DiscoverFilters = {
    region: string;
    language: string;
    interest: string;
};

type DiscoverFilterOptions = {
    regions: string[];
    languages: string[];
    interests: string[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedProfiles = {
    data: DiscoverProfile[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

const props = defineProps<{
    profiles: PaginatedProfiles;
    search: string;
    filters: DiscoverFilters;
    filterOptions: DiscoverFilterOptions;
}>();

const searchQuery = ref(props.search);
const selectedRegion = ref(props.filters.region);
const selectedLanguage = ref(props.filters.language);
const selectedInterest = ref(props.filters.interest);
const filtersOpen = ref(false);
const isMobile = useMediaQuery('(max-width: 767px)');
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const allFilterValue = '__all__';
const discoverSelectTriggerClass =
    'w-full border-input bg-background text-foreground hover:border-ring/70 hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border/90 dark:bg-input/35 dark:hover:bg-accent/45';
const discoverSelectContentClass =
    'border-border bg-popover text-popover-foreground shadow-xl shadow-black/30';
const discoverSelectItemClass =
    'focus:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] focus:text-popover-foreground data-[highlighted]:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] data-[highlighted]:text-popover-foreground data-[state=checked]:bg-action-primary data-[state=checked]:text-action-primary-foreground data-[state=checked]:focus:bg-action-primary data-[state=checked]:focus:text-action-primary-foreground';

const pageNumbers = computed(() => {
    const start = Math.max(
        1,
        Math.min(props.profiles.current_page - 2, props.profiles.last_page - 4),
    );
    const end = Math.min(props.profiles.last_page, start + 4);

    return Array.from(
        { length: Math.max(0, end - start + 1) },
        (_, index) => start + index,
    );
});

const pageUrl = (page: number) => {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));

    return `${url.pathname}${url.search}`;
};

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

    if (selectedLanguage.value) {
        params.language = selectedLanguage.value;
    }

    if (selectedInterest.value) {
        params.interest = selectedInterest.value;
    }

    if (
        query === props.search &&
        selectedRegion.value === props.filters.region &&
        selectedLanguage.value === props.filters.language &&
        selectedInterest.value === props.filters.interest
    ) {
        return;
    }

    router.get('/discover', params, {
        only: ['profiles', 'search', 'filters', 'filterOptions'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
        reset: ['profiles'],
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
    selectedRegion.value = '';
    selectedLanguage.value = '';
    selectedInterest.value = '';
    applyFilters();
};

const toSelectValue = (value: string) => value || allFilterValue;

const fromSelectValue = (value: string | number | boolean | null | undefined) =>
    typeof value === 'string' && value !== allFilterValue ? value : '';

const selectedRegionOption = computed({
    get: () => toSelectValue(selectedRegion.value),
    set: (value) => {
        selectedRegion.value = fromSelectValue(value);
        applyFilters();
    },
});

const selectedLanguageOption = computed({
    get: () => toSelectValue(selectedLanguage.value),
    set: (value) => {
        selectedLanguage.value = fromSelectValue(value);
        applyFilters();
    },
});

const selectedInterestOption = computed({
    get: () => toSelectValue(selectedInterest.value),
    set: (value) => {
        selectedInterest.value = fromSelectValue(value);
        applyFilters();
    },
});

const hasActiveFilters = computed(
    () =>
        selectedRegion.value !== '' ||
        selectedLanguage.value !== '' ||
        selectedInterest.value !== '',
);

const hasActiveDiscoverQuery = computed(
    () => searchQuery.value.trim() !== '' || hasActiveFilters.value,
);

watch(
    () => [props.search, props.filters] as const,
    ([search, filters]) => {
        if (searchQuery.value.trim() !== search) {
            searchQuery.value = search;
        }

        selectedRegion.value = filters.region;
        selectedLanguage.value = filters.language;
        selectedInterest.value = filters.interest;
    },
);

onBeforeUnmount(clearSearchTimer);

const profileLabel = (profile: DiscoverProfile) =>
    profile.display_name ?? `@${profile.username}`;

const avatarInitial = (profile: DiscoverProfile) =>
    profileLabel(profile).charAt(0).toUpperCase();

const visibleDetailCount = (profile: DiscoverProfile) =>
    Number(Boolean(profile.region)) +
    Number(Boolean(profile.languages?.length)) +
    Number(Boolean(profile.interests?.length));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mitglieder entdecken',
                href: '/discover',
            },
        ],
    },
});
</script>

<template>
    <Head title="Mitglieder entdecken" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
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
            title="Mitglieder entdecken"
            description="Finde Mitglieder aus deiner Region und mit passenden Interessen."
        />

        <PageSection>
            <Card
                class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="space-y-4">
                    <p
                        class="text-xs font-semibold tracking-wide text-primary uppercase"
                    >
                        Discover
                    </p>
                    <div class="max-w-3xl space-y-3">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Sichtbare Profile aus der Community
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Discover zeigt nur Profile und Angaben, die nach
                            Sichtbarkeit freigegeben und vom Server für diese
                            Ansicht ausgeliefert werden.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <form
                action="/discover"
                method="get"
                class="discover-filter-controls"
                @submit.prevent="runSearch"
            >
                <div class="grid gap-4">
                    <div class="grid max-w-xl gap-2">
                        <Label for="discover-search">
                            Profile durchsuchen
                        </Label>
                        <div class="relative">
                            <Input
                                id="discover-search"
                                v-model="searchQuery"
                                name="q"
                                type="search"
                                placeholder="Name, Benutzername oder Region"
                                autocomplete="off"
                                class="pr-11"
                                @input="handleSearchInput"
                            />
                            <Button
                                type="submit"
                                variant="ghost"
                                size="icon"
                                class="absolute top-1/2 right-1 size-8 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                aria-label="Profile suchen"
                            >
                                <Search class="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        class="w-full justify-between md:hidden"
                        :aria-expanded="filtersOpen"
                        aria-controls="discover-filters"
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
                        id="discover-filters"
                        class="gap-3 md:grid-cols-4 md:items-end"
                        :class="filtersOpen ? 'grid md:grid' : 'hidden md:grid'"
                    >
                        <div class="grid gap-2">
                            <Label for="discover-region">Region</Label>
                            <input
                                type="hidden"
                                name="region"
                                :value="selectedRegion"
                            />
                            <Select v-model="selectedRegionOption">
                                <SelectTrigger
                                    id="discover-region"
                                    :class="discoverSelectTriggerClass"
                                    aria-label="Region auswählen"
                                >
                                    <SelectValue placeholder="Alle Regionen" />
                                </SelectTrigger>
                                <SelectContent
                                    :class="discoverSelectContentClass"
                                >
                                    <SelectItem
                                        :value="allFilterValue"
                                        :class="discoverSelectItemClass"
                                    >
                                        Alle Regionen
                                    </SelectItem>
                                    <SelectItem
                                        v-for="region in filterOptions.regions"
                                        :key="region"
                                        :value="region"
                                        :class="discoverSelectItemClass"
                                    >
                                        {{ region }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="discover-language">Sprache</Label>
                            <input
                                type="hidden"
                                name="language"
                                :value="selectedLanguage"
                            />
                            <Select v-model="selectedLanguageOption">
                                <SelectTrigger
                                    id="discover-language"
                                    :class="discoverSelectTriggerClass"
                                    aria-label="Sprache auswählen"
                                >
                                    <SelectValue placeholder="Alle Sprachen" />
                                </SelectTrigger>
                                <SelectContent
                                    :class="discoverSelectContentClass"
                                >
                                    <SelectItem
                                        :value="allFilterValue"
                                        :class="discoverSelectItemClass"
                                    >
                                        Alle Sprachen
                                    </SelectItem>
                                    <SelectItem
                                        v-for="language in filterOptions.languages"
                                        :key="language"
                                        :value="language"
                                        :class="discoverSelectItemClass"
                                    >
                                        {{ language }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="discover-interest">Interesse</Label>
                            <input
                                type="hidden"
                                name="interest"
                                :value="selectedInterest"
                            />
                            <Select v-model="selectedInterestOption">
                                <SelectTrigger
                                    id="discover-interest"
                                    :class="discoverSelectTriggerClass"
                                    aria-label="Interesse auswählen"
                                >
                                    <SelectValue placeholder="Alle Interessen" />
                                </SelectTrigger>
                                <SelectContent
                                    :class="discoverSelectContentClass"
                                >
                                    <SelectItem
                                        :value="allFilterValue"
                                        :class="discoverSelectItemClass"
                                    >
                                        Alle Interessen
                                    </SelectItem>
                                    <SelectItem
                                        v-for="interest in filterOptions.interests"
                                        :key="interest"
                                        :value="interest"
                                        :class="discoverSelectItemClass"
                                    >
                                        {{ interest }}
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
            </form>
        </PageSection>

        <PageSection v-if="profiles.data.length === 0">
            <Card>
                <CardContent class="space-y-4 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        {{
                            search ||
                            filters.region ||
                            filters.language ||
                            filters.interest
                                ? 'Keine passenden Profile gefunden'
                                : 'Keine Profile sichtbar'
                        }}
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{
                            search ||
                            filters.region ||
                            filters.language ||
                            filters.interest
                                ? 'Passe deine Suche oder Filter an. Du kannst auch wieder alle sichtbaren Profile anzeigen.'
                                : 'Aktuell sind keine weiteren Profile für Discover freigegeben.'
                        }}
                    </p>
                    <div
                        v-if="hasActiveDiscoverQuery"
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="secondary"
                            class="w-full sm:w-auto"
                            @click="resetFilters"
                        >
                            Filter zurücksetzen
                        </Button>
                        <Button as-child class="w-full sm:w-auto">
                            <Link href="/discover">
                                Alle Profile anzeigen
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <InfiniteScroll
                data="profiles"
                only-next
                :manual="!isMobile"
                :preserve-url="false"
                :params="{ only: ['profiles'] }"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="profile in profiles.data"
                    :key="profile.username"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-4 p-5">
                        <div class="flex items-start gap-4">
                            <ProfileAvatar
                                :photo-url="profile.profile_photo_url"
                                :alt="profileLabel(profile)"
                                :fallback="avatarInitial(profile)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

                            <div class="min-w-0 flex-1 space-y-1.5">
                                <h2
                                    class="truncate text-lg font-bold tracking-tight text-card-foreground"
                                >
                                    {{ profileLabel(profile) }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ profile.username }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="profile.region"
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-foreground dark:bg-input/30"
                            >
                                {{ profile.region }}
                            </span>
                            <ContactStatusBadge
                                :status="profile.contact_status"
                            />
                            <span
                                v-if="
                                    profile.is_following &&
                                    profile.contact_status !== 'connected'
                                "
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                Du folgst
                            </span>
                            <span
                                v-if="
                                    profile.is_followed_by && !profile.is_mutual
                                "
                                class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                            >
                                Folgt dir
                            </span>
                        </div>

                        <div v-if="profile.bio" class="space-y-2">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Bio
                            </p>
                            <p
                                class="text-sm leading-6 whitespace-pre-wrap text-muted-foreground"
                            >
                                {{ profile.bio }}
                            </p>
                        </div>
                        <p
                            v-else
                            class="rounded-md border border-dashed border-border/80 bg-muted/30 px-3 py-2 text-sm leading-6 text-muted-foreground"
                        >
                            Dieses Mitglied hat noch keine Bio hinterlegt.
                        </p>

                        <div class="space-y-2.5">
                            <div
                                v-if="profile.common_languages?.length"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Gemeinsame Sprachen
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="language in profile.common_languages.slice(
                                            0,
                                            2,
                                        )"
                                        :key="language"
                                        variant="secondary"
                                    >
                                        {{ language }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="profile.common_interests?.length"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Gemeinsame Interessen
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="interest in profile.common_interests.slice(
                                            0,
                                            3,
                                        )"
                                        :key="interest"
                                        variant="outline"
                                        class="border-primary/30 bg-primary/10"
                                    >
                                        {{ interest }}
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="profile.languages?.length"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Sprachen
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="language in profile.languages.slice(
                                            0,
                                            3,
                                        )"
                                        :key="language"
                                        variant="secondary"
                                    >
                                        {{ language }}
                                    </Badge>
                                    <Badge
                                        v-if="profile.languages.length > 3"
                                        variant="outline"
                                        class="text-muted-foreground"
                                    >
                                        +{{ profile.languages.length - 3 }}
                                        weitere
                                    </Badge>
                                </div>
                            </div>

                            <div
                                v-if="profile.interests?.length"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Interessen
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge
                                        v-for="interest in profile.interests.slice(
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
                                        v-if="profile.interests.length > 3"
                                        variant="outline"
                                        class="text-muted-foreground"
                                    >
                                        +{{ profile.interests.length - 3 }}
                                        weitere
                                    </Badge>
                                </div>
                            </div>

                            <p
                                v-if="visibleDetailCount(profile) === 0"
                                class="rounded-md border border-border bg-background/60 px-3 py-2 text-sm leading-6 text-muted-foreground dark:bg-input/20"
                            >
                                Einige Profilinformationen sind nur für Kontakte
                                sichtbar.
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <ContactActions
                                :can-follow="profile.can_follow"
                                :can-send-contact-request="
                                    profile.can_send_contact_request
                                "
                                :contact-request-id="
                                    profile.incoming_contact_request_id
                                "
                                :contact-request-unavailable-reason="
                                    profile.contact_request_unavailable_reason
                                "
                                :is-following="profile.is_following"
                                stay-on-page
                                :status="profile.contact_status"
                                :user-id="profile.contact_user_id"
                                :username="profile.username"
                            />

                            <Button as-child variant="secondary" class="w-full">
                                <Link :href="profileUrl(profile.username)">
                                    Profil ansehen
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <template #loading>
                    <div
                        class="flex items-center justify-center gap-2 py-5 text-sm text-muted-foreground md:hidden"
                    >
                        <Spinner />
                        Lade weitere Profile...
                    </div>
                </template>
            </InfiniteScroll>

            <nav
                v-if="profiles.last_page > 1"
                class="mt-6 hidden items-center justify-center gap-2 md:flex"
                aria-label="Discover Seiten"
            >
                <Button
                    as-child
                    variant="secondary"
                    :disabled="!profiles.prev_page_url"
                >
                    <Link
                        v-if="profiles.prev_page_url"
                        :href="profiles.prev_page_url"
                    >
                        ← Vorherige
                    </Link>
                    <span v-else>← Vorherige</span>
                </Button>

                <Button
                    v-for="page in pageNumbers"
                    :key="page"
                    as-child
                    :variant="
                        page === profiles.current_page ? 'default' : 'secondary'
                    "
                    size="icon"
                >
                    <Link :href="pageUrl(page)">
                        {{ page }}
                    </Link>
                </Button>

                <Button
                    as-child
                    variant="secondary"
                    :disabled="!profiles.next_page_url"
                >
                    <Link
                        v-if="profiles.next_page_url"
                        :href="profiles.next_page_url"
                    >
                        Nächste →
                    </Link>
                    <span v-else>Nächste →</span>
                </Button>
            </nav>
        </PageSection>
    </div>
</template>
