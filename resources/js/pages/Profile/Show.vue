<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBackButton from '@/components/AppBackButton.vue';
import ContactActions from '@/components/ContactActions.vue';
import ContactStatusBadge from '@/components/ContactStatusBadge.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileMoreActions from '@/components/ProfileMoreActions.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ContactStatus } from '@/types';

type PublicProfile = {
    can_follow?: boolean;
    can_send_contact_request?: boolean;
    contact_user_id?: number;
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

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-4 overflow-x-auto p-4 sm:p-6"
    >
        <AppBackButton
            v-if="!props.profile.isOwnProfile"
            fallback="/discover"
            label="Zurück zur Übersicht"
            class="hidden md:inline-flex"
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="p-4 sm:p-5">
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div
                            class="flex min-w-0 flex-1 flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            <div
                                class="flex size-20 shrink-0 items-center justify-center rounded-full border border-primary/25 bg-primary/15 text-3xl font-semibold text-primary shadow-inner"
                            >
                                {{ avatarInitial }}
                            </div>

                            <div class="min-w-0 flex-1 space-y-3">
                                <div class="min-w-0">
                                    <h1
                                        class="truncate text-2xl font-semibold tracking-tight sm:text-3xl"
                                    >
                                        {{ displayName }}
                                    </h1>
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
                                        v-if="
                                            !props.profile.isOwnProfile &&
                                            !props.profile.interaction_blocked
                                        "
                                        :status="props.profile.contact_status"
                                    />
                                    <span
                                        v-if="
                                            props.profile.is_blocked_by_viewer
                                        "
                                        class="rounded-full border border-destructive/30 bg-destructive/10 px-3 py-1 text-xs font-medium text-destructive"
                                    >
                                        Benutzer blockiert
                                    </span>
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
                                    <span
                                        v-if="props.profile.region"
                                        class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-foreground dark:bg-input/30"
                                    >
                                        {{ props.profile.region }}
                                    </span>
                                </div>

                                <p
                                    v-if="props.profile.bio"
                                    class="max-w-3xl text-sm leading-6 whitespace-pre-wrap text-muted-foreground sm:text-base"
                                >
                                    {{ props.profile.bio }}
                                </p>
                                <p v-else class="text-sm text-muted-foreground">
                                    Dieses Profil hat noch keine Bio sichtbar
                                    gemacht.
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex w-full flex-col gap-2 lg:w-52 lg:shrink-0"
                        >
                            <Button v-if="props.editProfileHref" as-child>
                                <Link :href="props.editProfileHref">
                                    Profil bearbeiten
                                </Link>
                            </Button>

                            <ContactActions
                                v-else-if="
                                    !props.profile.isOwnProfile &&
                                    props.profile.contact_user_id &&
                                    !props.profile.interaction_blocked
                                "
                                :can-follow="props.profile.can_follow ?? false"
                                :can-send-contact-request="
                                    props.profile.can_send_contact_request ??
                                    false
                                "
                                :contact-request-id="
                                    props.profile.incoming_contact_request_id
                                "
                                :contact-request-unavailable-reason="
                                    props.profile
                                        .contact_request_unavailable_reason
                                "
                                :is-following="props.profile.is_following"
                                :status="props.profile.contact_status"
                                :user-id="props.profile.contact_user_id"
                                :username="props.profile.username"
                            />

                            <ProfileMoreActions
                                v-if="!props.profile.isOwnProfile"
                                :is-blocked="props.profile.is_blocked_by_viewer"
                                :username="props.profile.username"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-if="hasVisibleDetails">
            <div class="grid gap-4 md:grid-cols-2">
                <Card v-if="props.profile.languages?.length">
                    <CardContent class="space-y-3 p-4 sm:p-5">
                        <h2 class="text-base font-semibold">Sprachen</h2>
                        <div class="flex flex-wrap gap-2.5">
                            <span
                                v-for="language in props.profile.languages"
                                :key="language"
                                class="rounded-full border border-border bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground"
                            >
                                {{ language }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="props.profile.interests?.length">
                    <CardContent class="space-y-3 p-4 sm:p-5">
                        <h2 class="text-base font-semibold">Interessen</h2>
                        <div class="flex flex-wrap gap-2.5">
                            <span
                                v-for="interest in props.profile.interests"
                                :key="interest"
                                class="rounded-full border border-primary/30 bg-primary/10 px-4 py-2 text-sm font-medium text-foreground"
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
                        Sprachen und Interessen sind für diese Ansicht aktuell
                        nicht freigegeben.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
