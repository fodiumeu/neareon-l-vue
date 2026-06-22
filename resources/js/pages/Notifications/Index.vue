<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Handshake, Users } from 'lucide-vue-next';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

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
    visual_kind: 'actor' | 'contact-requests' | 'followers' | 'message';
    cta_label: string;
    actor: {
        display_name: string;
        profile_photo_url: string | null;
        initials: string;
    } | null;
    open_url: string;
};

const props = defineProps<{
    notificationItems: InternalNotification[];
}>();

const hasUnreadNotifications = () =>
    props.notificationItems.some(
        (notification) => notification.read_at === null,
    );

const startOfDay = (date: Date) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate());

const formatNotificationDate = (value: string) => {
    const date = new Date(value);
    const today = startOfDay(new Date());
    const notificationDay = startOfDay(date);
    const startOfWeek = new Date(today);
    const dayOfWeek = startOfWeek.getDay();
    startOfWeek.setDate(
        startOfWeek.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1),
    );
    const differenceInDays = Math.round(
        (today.getTime() - notificationDay.getTime()) / 86_400_000,
    );

    if (differenceInDays === 0) {
        return new Intl.DateTimeFormat('de-DE', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    if (differenceInDays === 1) {
        return 'Gestern';
    }

    if (notificationDay >= startOfWeek) {
        return new Intl.DateTimeFormat('de-DE', {
            weekday: 'long',
        }).format(date);
    }

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

const openNotification = (notification: InternalNotification) => {
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
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            title="Benachrichtigungen"
            description="Hier findest du Neuigkeiten zu Kontakten, Followern und Nachrichten."
        />

        <div v-if="hasUnreadNotifications()" class="flex justify-end">
            <Form
                action="/notifications/read-all"
                method="patch"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Alle als gelesen markieren
                </Button>
            </Form>
        </div>

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
                <Link
                    v-for="notification in notificationItems"
                    :key="notification.id"
                    :href="notification.open_url"
                    class="group block rounded-xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60"
                    :aria-label="`${notification.title}: ${notification.cta_label}`"
                    @keydown.space.prevent="openNotification(notification)"
                >
                    <Card
                        class="transition-[border-color,box-shadow,transform,background-color] duration-200 motion-reduce:transition-none md:group-hover:-translate-y-0.5 md:group-hover:border-primary/50 md:group-hover:shadow-lg md:group-hover:shadow-primary/10"
                        :class="
                            notification.read_at === null
                                ? 'border-primary/60 bg-primary/10 shadow-sm shadow-primary/10'
                                : 'border-border/70 bg-card/80'
                        "
                    >
                        <CardContent class="min-h-28 space-y-4">
                            <div class="flex min-w-0 gap-3 sm:gap-4">
                                <div
                                    v-if="
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
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatNotificationDate(
                                                notification.created_at,
                                            )
                                        }}
                                    </time>
                                </div>
                            </div>

                            <span
                                class="inline-flex min-h-9 w-full items-center justify-center rounded-md border border-input bg-secondary px-4 py-2 text-sm font-medium text-secondary-foreground shadow-xs transition-colors group-hover:bg-secondary/80 sm:w-auto"
                                aria-hidden="true"
                            >
                                {{ notification.cta_label }}
                            </span>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </PageSection>
    </div>
</template>
