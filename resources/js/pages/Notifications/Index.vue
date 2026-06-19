<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
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
};

const props = defineProps<{
    notificationItems: InternalNotification[];
}>();

const hasUnreadNotifications = () =>
    props.notificationItems.some(
        (notification) => notification.read_at === null,
    );

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

const formatRelativeDate = (value: string) => {
    const differenceInSeconds = Math.round(
        (new Date(value).getTime() - Date.now()) / 1000,
    );
    const absoluteDifference = Math.abs(differenceInSeconds);
    const formatter = new Intl.RelativeTimeFormat('de-DE', {
        numeric: 'auto',
    });

    if (absoluteDifference < 60) {
        return formatter.format(differenceInSeconds, 'second');
    }

    if (absoluteDifference < 3600) {
        return formatter.format(Math.round(differenceInSeconds / 60), 'minute');
    }

    if (absoluteDifference < 86400) {
        return formatter.format(Math.round(differenceInSeconds / 3600), 'hour');
    }

    return formatter.format(Math.round(differenceInSeconds / 86400), 'day');
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
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
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
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit keine Benachrichtigungen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="space-y-3">
                <Card
                    v-for="notification in notificationItems"
                    :key="notification.id"
                    :class="
                        notification.read_at === null
                            ? 'border-primary/30 bg-primary/5'
                            : 'bg-card/95'
                    "
                >
                    <CardContent class="space-y-3">
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
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
                            </div>

                            <time
                                :datetime="notification.created_at"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                <template v-if="notification.is_message_group">
                                    Letzte Nachricht
                                    {{
                                        formatRelativeDate(
                                            notification.created_at,
                                        )
                                    }}
                                </template>
                                <template v-else>
                                    {{ formatDate(notification.created_at) }}
                                </template>
                            </time>
                        </div>

                        <Button as-child variant="secondary">
                            <Link :href="notification.target_url">
                                Ziel öffnen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
