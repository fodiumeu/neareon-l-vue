<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ContactStatusBadge from '@/components/ContactStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import type { ContactStatus } from '@/types';

type PublicProfile = {
    username: string;
    isOwnProfile: boolean;
    is_following: boolean;
    is_followed_by: boolean;
    is_mutual: boolean;
    contact_status: ContactStatus;
    display_name?: string;
    bio?: string | null;
    region?: string | null;
    languages?: string[] | null;
    interests?: string[] | null;
};

const props = defineProps<{
    profile: PublicProfile;
    editProfileHref?: string | null;
}>();

const displayName = computed(
    () => props.profile.display_name ?? `@${props.profile.username}`,
);
const avatarInitial = computed(() => displayName.value.charAt(0).toUpperCase());
const hasVisibleDetails = computed(
    () =>
        Boolean(props.profile.region) ||
        Boolean(props.profile.languages?.length) ||
        Boolean(props.profile.interests?.length),
);
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profil',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="profile.display_name ?? `@${profile.username}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            :title="displayName"
            :description="`@${profile.username}`"
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="space-y-6">
                    <div
                        class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex min-w-0 gap-4">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center rounded-full border border-primary/25 bg-primary/15 text-2xl font-semibold text-primary shadow-inner"
                            >
                                {{ avatarInitial }}
                            </div>

                            <div class="min-w-0 space-y-3">
                                <div class="min-w-0 space-y-1">
                                    <h2
                                        class="truncate text-2xl font-semibold tracking-tight"
                                    >
                                        {{ displayName }}
                                    </h2>
                                    <p
                                        class="truncate text-sm text-muted-foreground"
                                    >
                                        @{{ props.profile.username }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-if="props.profile.isOwnProfile"
                                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                    >
                                        Eigenes Profil
                                    </span>
                                    <ContactStatusBadge
                                        v-if="!props.profile.isOwnProfile"
                                        :status="props.profile.contact_status"
                                    />
                                    <span
                                        v-if="
                                            props.profile.is_following &&
                                            props.profile.contact_status !==
                                                'connected'
                                        "
                                        class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                                    >
                                        Du folgst
                                    </span>
                                    <span
                                        v-if="
                                            props.profile.is_followed_by &&
                                            !props.profile.is_mutual
                                        "
                                        class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground dark:bg-input/30"
                                    >
                                        Folgt dir
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-40"
                        >
                            <Button v-if="props.editProfileHref" as-child>
                                <Link :href="props.editProfileHref">
                                    Profil bearbeiten
                                </Link>
                            </Button>

                            <Form
                                v-else-if="!props.profile.isOwnProfile"
                                :action="`/u/${props.profile.username}/follow`"
                                :method="
                                    props.profile.is_following
                                        ? 'delete'
                                        : 'post'
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :variant="
                                        props.profile.is_following
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    :disabled="processing"
                                    class="w-full"
                                >
                                    <Spinner v-if="processing" />
                                    {{
                                        props.profile.is_following
                                            ? 'Entfolgen'
                                            : 'Folgen'
                                    }}
                                </Button>
                            </Form>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-4">
                    <div v-if="props.profile.bio" class="space-y-2">
                        <h2 class="text-base font-semibold">Bio</h2>
                        <p
                            class="max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base sm:leading-7"
                        >
                            {{ props.profile.bio }}
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Dieses Profil hat noch keine Bio sichtbar gemacht.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-if="hasVisibleDetails">
            <div class="grid gap-4 md:grid-cols-3">
                <Card v-if="props.profile.region">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-semibold">Region</h2>
                        <p
                            class="inline-flex rounded-full border border-border bg-background/70 px-3 py-1 text-sm font-medium text-foreground dark:bg-input/30"
                        >
                            {{ props.profile.region }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="props.profile.languages?.length">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-semibold">Sprachen</h2>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="language in props.profile.languages"
                                :key="language"
                                class="rounded-full border border-border bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground"
                            >
                                {{ language }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="props.profile.interests?.length">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-semibold">Interessen</h2>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="interest in props.profile.interests"
                                :key="interest"
                                class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-foreground"
                            >
                                {{ interest }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection v-else>
            <Card>
                <CardContent class="space-y-2">
                    <h2 class="text-base font-semibold">
                        Keine weiteren Angaben sichtbar
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Region, Sprachen und Interessen sind für diese Ansicht
                        aktuell nicht freigegeben.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
