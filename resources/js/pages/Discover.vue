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

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Entdecken"
            description="Finde sichtbare Profile aus der NEAREON-Community."
        />

        <PageSection v-if="profiles.length === 0" padded>
            <Card>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        Aktuell sind keine weiteren Profile sichtbar.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="profile in profiles" :key="profile.username">
                    <CardContent class="space-y-4">
                        <div class="space-y-1">
                            <h2 class="text-base font-medium">
                                {{
                                    profile.display_name ??
                                    `@${profile.username}`
                                }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                @{{ profile.username }}
                            </p>
                        </div>

                        <p
                            v-if="profile.bio"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            {{ profile.bio }}
                        </p>

                        <div
                            v-if="
                                profile.region ||
                                profile.languages?.length ||
                                profile.interests?.length
                            "
                            class="space-y-2 text-sm text-muted-foreground"
                        >
                            <p v-if="profile.region">
                                Region: {{ profile.region }}
                            </p>
                            <p v-if="profile.languages?.length">
                                Sprachen: {{ profile.languages.join(', ') }}
                            </p>
                            <p v-if="profile.interests?.length">
                                Interessen: {{ profile.interests.join(', ') }}
                            </p>
                        </div>

                        <p
                            v-if="profile.is_mutual"
                            class="text-sm text-muted-foreground"
                        >
                            Gegenseitiges Folgen
                        </p>
                        <p
                            v-else-if="profile.is_following"
                            class="text-sm text-muted-foreground"
                        >
                            Du folgst diesem Profil
                        </p>
                        <p
                            v-else-if="profile.is_followed_by"
                            class="text-sm text-muted-foreground"
                        >
                            Dieses Profil folgt dir
                        </p>

                        <Button as-child variant="secondary">
                            <Link :href="`/u/${profile.username}`">
                                Profil ansehen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
