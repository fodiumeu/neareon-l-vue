<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Handshake, Users } from 'lucide-vue-next';
import CommunityBackLink from '@/components/CommunityBackLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    formatContactRelativeTime,
    formatContactRelativeTimeTitle,
} from '@/lib/contactRelativeTime';

type NotificationActor = {
    display_name: string;
    profile_photo_url: string | null;
    initials: string;
};

type InternalNotification = {
    id: string;
    type: string;
    title: string;
    message: string;
    target_url: string;
    created_at: string;
    read_at: string | null;
    notification_count: number;
    is_message_group: boolean;
    is_activity_group: boolean;
    actors: string[];
    actor_previews: NotificationActor[];
    visual_kind: 'actor' | 'contact-requests' | 'followers' | 'message';
    cta_label: string | null;
    actor: NotificationActor | null;
    open_url: string | null;
};

type BackLink = {
    href: string;
    label: string;
};

defineProps<{
    backLink: BackLink | null;
    notificationItems: InternalNotification[];
}>();

const openNotification = (notification: InternalNotification) => {
    if (!notification.open_url) {
        return;
    }

    router.visit(notification.open_url);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Benachrichtigungen',
                href: '/notifications',
            },
        ],
    },
});
</script>

<template>
    <Head title="Benachrichtigungen" />

    <div
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <CommunityBackLink
            v-if="backLink"
            :href="backLink.href"
            :label="backLink.label"
        />

        <PageHeader
            title="Benachrichtigungen"
            description="Hier findest du Neuigkeiten zu Kontakten, Followern und Nachrichten."
        />

        <PageSection v-if="notificationItems.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="space-y-2 text-center sm:text-left">
                    <h2 class="font-semibold">Noch keine Benachrichtigungen</h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Hier erscheinen neue Aktivitäten aus deiner Community.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-3">
                <component
                    :is="notification.open_url ? Link : 'div'"
                    v-for="notification in notificationItems"
                    :key="notification.id"
                    :href="notification.open_url ?? undefined"
                    class="block rounded-xl"
                    :class="{
                        'group outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60':
                            notification.open_url,
                    }"
                    :aria-label="
                        notification.cta_label
                            ? `${notification.title}: ${notification.cta_label}`
                            : undefined
                    "
                    @keydown.space.prevent="openNotification(notification)"
                >
                    <Card
                        class="transition-[border-color,box-shadow,transform,background-color] duration-200 motion-reduce:transition-none"
                        :class="[
                            notification.read_at === null
                                ? 'border-primary/60 bg-primary/10 shadow-sm shadow-primary/10'
                                : 'border-border/70 bg-card/80',
                            notification.open_url
                                ? 'md:group-hover:-translate-y-0.5 md:group-hover:border-primary/50 md:group-hover:shadow-lg md:group-hover:shadow-primary/10'
                                : '',
                        ]"
                    >
                        <CardContent class="space-y-3 p-4 sm:p-5">
                            <div class="flex min-w-0 gap-3 sm:gap-4">
                                <div
                                    v-if="
                                        notification.visual_kind ===
                                            'followers' &&
                                        notification.actor_previews.length > 0
                                    "
                                    class="flex shrink-0 -space-x-3 py-1"
                                    :aria-label="`${notification.actor_previews.length} aktuelle Follower`"
                                >
                                    <ProfileAvatar
                                        v-for="(
                                            actor, index
                                        ) in notification.actor_previews"
                                        :key="`${actor.display_name}-${index}`"
                                        :photo-url="actor.profile_photo_url"
                                        :alt="actor.display_name"
                                        :fallback="actor.initials"
                                        class="size-10 border-2 border-card shadow-sm sm:size-12"
                                    />
                                </div>
                                <div
                                    v-else-if="
                                        notification.visual_kind === 'followers'
                                    "
                                    class="flex size-12 shrink-0 items-center justify-center rounded-full border border-primary/35 bg-primary/15 text-primary sm:size-14"
                                    aria-hidden="true"
                                >
                                    <Users class="size-6 sm:size-7" />
                                </div>
                                <div
                                    v-else-if="
                                        notification.visual_kind ===
                                        'contact-requests'
                                    "
                                    class="flex size-12 shrink-0 items-center justify-center rounded-full border border-primary/35 bg-primary/15 text-primary sm:size-14"
                                    aria-hidden="true"
                                >
                                    <Handshake class="size-6 sm:size-7" />
                                </div>
                                <ProfileAvatar
                                    v-else-if="notification.actor"
                                    :photo-url="
                                        notification.actor.profile_photo_url
                                    "
                                    :alt="notification.actor.display_name"
                                    :fallback="notification.actor.initials"
                                    class="size-12 shrink-0 sm:size-14"
                                />
                                <div
                                    v-else
                                    class="flex size-12 shrink-0 items-center justify-center rounded-full border border-primary/25 bg-primary/15 font-semibold text-primary sm:size-14"
                                    aria-hidden="true"
                                >
                                    {{ notification.title.charAt(0) }}
                                </div>

                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h2 class="font-semibold">
                                            {{ notification.title }}
                                        </h2>
                                        <Badge
                                            :variant="
                                                notification.read_at === null
                                                    ? 'default'
                                                    : 'secondary'
                                            "
                                            class="px-2.5 py-1"
                                        >
                                            {{
                                                notification.read_at === null
                                                    ? 'Ungelesen'
                                                    : 'Gelesen'
                                            }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ notification.message }}
                                    </p>
                                    <ul
                                        v-if="
                                            notification.is_activity_group &&
                                            notification.actors.length > 0
                                        "
                                        class="flex flex-wrap gap-2 pt-2"
                                        :aria-label="notification.title"
                                    >
                                        <li
                                            v-for="actor in notification.actors"
                                            :key="actor"
                                            class="rounded-full border border-border bg-background/70 px-3 py-1 text-xs font-medium text-foreground dark:bg-input/30"
                                        >
                                            {{ actor }}
                                        </li>
                                    </ul>

                                    <time
                                        :datetime="notification.created_at"
                                        :title="
                                            formatContactRelativeTimeTitle(
                                                notification.created_at,
                                            )
                                        "
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatContactRelativeTime(
                                                notification.created_at,
                                            )
                                        }}
                                    </time>
                                </div>
                            </div>

                            <span
                                v-if="notification.cta_label"
                                class="inline-flex h-9 w-full items-center justify-center rounded-md border border-border/80 bg-secondary px-4 text-sm font-medium text-secondary-foreground shadow-xs transition-colors group-hover:bg-secondary/80 sm:w-auto"
                                aria-hidden="true"
                            >
                                {{ notification.cta_label }}
                            </span>
                        </CardContent>
                    </Card>
                </component>
            </div>
        </PageSection>
    </div>
</template>
