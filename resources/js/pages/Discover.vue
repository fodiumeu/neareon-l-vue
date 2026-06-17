<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type DiscoverProfile = {
    username: string;
    isOwnProfile: boolean;
    is_following: boolean;
    is_followed_by: boolean;
    is_mutual: boolean;
    display_name?: string;
    bio?: string | null;
    region?: string | null;
    languages?: string[] | null;
    interests?: string[] | null;
};

defineProps<{
    profiles: DiscoverProfile[];
}>();

const profileLabel = (profile: DiscoverProfile) =>
    profile.display_name ?? `@${profile.username}`;

const avatarInitial = (profile: DiscoverProfile) =>
    profileLabel(profile).charAt(0).toUpperCase();

const visibleDetailCount = (profile: DiscoverProfile) =>
    Number(Boolean(profile.region)) +
    Number(Boolean(profile.languages?.length)) +
    Number(Boolean(profile.interests?.length));

const hasAnySocialStatus = (profile: DiscoverProfile) =>
    profile.is_mutual || profile.is_following || profile.is_followed_by;

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Entdecken',
                href: '/discover',
            },
        ],
    },
});
</script>

<template>
    <Head title="Entdecken" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Entdecken"
            description="Finde sichtbare Profile aus der NEAREON-Community."
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

        <PageSection v-if="profiles.length === 0">
            <Card>
                <CardContent class="space-y-2 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        Keine Profile sichtbar
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Aktuell sind keine weiteren Profile für Discover
                        freigegeben.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="profile in profiles"
                    :key="profile.username"
                    class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-5">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-full border border-primary/25 bg-primary/15 text-base font-semibold text-primary"
                            >
                                {{ avatarInitial(profile) }}
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
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
                            <span
                                v-if="profile.is_mutual"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                Gegenseitiges Folgen
                            </span>
                            <span
                                v-else-if="profile.is_following"
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
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ profile.bio }}
                            </p>
                        </div>
                        <p
                            v-else
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Dieses Profil hat noch keine Bio sichtbar gemacht.
                        </p>

                        <div class="space-y-3">
                            <div
                                v-if="profile.languages?.length"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Sprachen
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="language in profile.languages"
                                        :key="language"
                                        class="rounded-full border border-border bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground"
                                    >
                                        {{ language }}
                                    </span>
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
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="interest in profile.interests"
                                        :key="interest"
                                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-foreground"
                                    >
                                        {{ interest }}
                                    </span>
                                </div>
                            </div>

                            <p
                                v-if="visibleDetailCount(profile) === 0"
                                class="rounded-md border border-border bg-background/60 px-3 py-2 text-sm leading-6 text-muted-foreground dark:bg-input/20"
                            >
                                Weitere Angaben sind für Discover aktuell nicht
                                sichtbar.
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <p
                                v-if="!hasAnySocialStatus(profile)"
                                class="rounded-md border border-border bg-background/60 px-3 py-2 text-sm text-muted-foreground dark:bg-input/20"
                            >
                                Noch keine Follow-Verbindung sichtbar.
                            </p>

                            <Button as-child variant="secondary" class="w-full">
                                <Link :href="`/u/${profile.username}`">
                                    Profil ansehen
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
