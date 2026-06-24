<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CommunityBackLink from '@/components/CommunityBackLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    formatContactRelativeTime,
    formatContactRelativeTimeTitle,
} from '@/lib/contactRelativeTime';
import { profileUrl } from '@/lib/relationshipActions';
import type { ContactStatus } from '@/types';

type FollowedProfile = {
    id: number;
    display_name: string;
    username: string;
    profile_photo_url: string | null;
    followed_at: string;
    is_followed_by: boolean;
    contact_status: ContactStatus;
};

type PaginatedFollowing = {
    data: FollowedProfile[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    following: PaginatedFollowing;
}>();

const pageNumbers = computed(() => {
    const start = Math.max(
        1,
        Math.min(
            props.following.current_page - 2,
            props.following.last_page - 4,
        ),
    );
    const end = Math.min(props.following.last_page, start + 4);

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

const avatarInitial = (followedProfile: FollowedProfile) =>
    followedProfile.display_name.charAt(0).toUpperCase();

const hasOpenRequest = (followedProfile: FollowedProfile) =>
    ['incoming_request', 'outgoing_request'].includes(
        followedProfile.contact_status,
    );

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ich folge',
                href: '/following',
            },
        ],
    },
});
</script>

<template>
    <Head title="Ich folge" />

    <div
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <CommunityBackLink />

        <PageHeader
            title="Ich folge"
            description="Hier siehst du alle Profile, denen du folgst. Die neuesten Follow-Beziehungen stehen zuerst."
        />

        <PageSection v-if="following.data.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="space-y-4 text-center sm:text-left">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold tracking-tight">
                            Du folgst noch niemandem.
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Entdecke Mitglieder und folge interessanten
                            Profilen.
                        </p>
                    </div>

                    <Button as-child>
                        <Link href="/discover">Mitglieder entdecken</Link>
                    </Button>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="followedProfile in following.data"
                    :key="followedProfile.id"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-3 p-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <ProfileAvatar
                                :photo-url="followedProfile.profile_photo_url"
                                :alt="followedProfile.display_name"
                                :fallback="avatarInitial(followedProfile)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ followedProfile.display_name }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ followedProfile.username }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Badge variant="secondary">Du folgst</Badge>
                            <Badge
                                v-if="
                                    followedProfile.contact_status ===
                                    'connected'
                                "
                            >
                                Kontakt
                            </Badge>
                            <Badge
                                v-if="hasOpenRequest(followedProfile)"
                                variant="outline"
                                class="border-primary/30 bg-primary/10"
                            >
                                Anfrage offen
                            </Badge>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Du folgst seit:
                            <time
                                :datetime="followedProfile.followed_at"
                                :title="
                                    formatContactRelativeTimeTitle(
                                        followedProfile.followed_at,
                                    )
                                "
                            >
                                {{
                                    formatContactRelativeTime(
                                        followedProfile.followed_at,
                                    )
                                }}
                            </time>
                        </p>

                        <div class="mt-auto pt-1">
                            <Button as-child variant="secondary" class="w-full">
                                <Link
                                    :href="profileUrl(followedProfile.username)"
                                >
                                    Profil ansehen
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="following.last_page > 1"
                class="mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Ich-folge Seiten"
            >
                <Button
                    as-child
                    variant="secondary"
                    :disabled="!following.prev_page_url"
                >
                    <Link
                        v-if="following.prev_page_url"
                        :href="following.prev_page_url"
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
                        page === following.current_page
                            ? 'default'
                            : 'secondary'
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
                    :disabled="!following.next_page_url"
                >
                    <Link
                        v-if="following.next_page_url"
                        :href="following.next_page_url"
                    >
                        Nächste →
                    </Link>
                    <span v-else>Nächste →</span>
                </Button>
            </nav>
        </PageSection>
    </div>
</template>
