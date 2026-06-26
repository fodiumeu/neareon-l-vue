<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type GroupContext = {
    id: number;
    name: string;
    slug: string;
    url: string;
};

type GroupMember = {
    id: number;
    role: 'owner' | 'moderator' | 'member';
    role_label: string;
    status: string;
    joined_at?: string | null;
    user: {
        id: number;
        name: string;
        username?: string | null;
        profile_photo_url?: string | null;
        profile_url?: string | null;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedMembers = {
    data: GroupMember[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

defineProps<{
    group: GroupContext;
    members: PaginatedMembers;
}>();

const avatarInitial = (name: string) => name.charAt(0).toUpperCase();

const roleBadgeClass = (role: GroupMember['role']) =>
    role === 'owner'
        ? 'border-primary/30 bg-primary/10 text-primary'
        : role === 'moderator'
          ? 'border-border bg-background/70 text-foreground dark:bg-input/30'
          : 'border-border bg-background/70 text-muted-foreground dark:bg-input/30';

const joinedAtLabel = (value?: string | null) => {
    if (!value) {
        return 'Beitrittsdatum unbekannt';
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gruppenmitglieder',
                href: '/groups',
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Mitglieder – ${group.name}`" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <Button
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link :href="group.url" class="min-w-0 truncate">
                ← Zurück zur Gruppe
            </Link>
        </Button>

        <PageHeader
            title="Mitglieder"
            description="Alle aktiven Mitglieder dieser Gruppe."
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="space-y-2 p-5">
                    <p
                        class="text-xs font-semibold tracking-wide text-primary uppercase"
                    >
                        Gruppe
                    </p>
                    <h2
                        class="break-words text-xl font-semibold tracking-tight"
                    >
                        {{ group.name }}
                    </h2>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-if="members.data.length === 0">
            <Card>
                <CardContent class="space-y-2 text-center sm:text-left">
                    <h2 class="text-base font-medium">
                        Noch keine aktiven Mitglieder
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Sobald Mitglieder dieser Gruppe beitreten, erscheinen
                        sie hier.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid min-w-0 gap-3 lg:grid-cols-2">
                <Card
                    v-for="member in members.data"
                    :key="member.id"
                    class="min-w-0 border-border/80 bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent
                        class="flex min-w-0 flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <ProfileAvatar
                                :photo-url="member.user.profile_photo_url"
                                :alt="member.user.name"
                                :fallback="avatarInitial(member.user.name)"
                                class="size-12 shrink-0 shadow-sm"
                            />

                            <div class="min-w-0 space-y-1">
                                <div
                                    class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center"
                                >
                                    <p class="truncate text-sm font-medium">
                                        {{ member.user.name }}
                                    </p>
                                    <Badge
                                        variant="outline"
                                        class="w-fit shrink-0"
                                        :class="roleBadgeClass(member.role)"
                                    >
                                        {{ member.role_label }}
                                    </Badge>
                                </div>
                                <p
                                    v-if="member.user.username"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ member.user.username }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Mitglied seit
                                    {{ joinedAtLabel(member.joined_at) }}
                                </p>
                            </div>
                        </div>

                        <Button
                            v-if="member.user.profile_url"
                            as-child
                            variant="secondary"
                            class="w-full sm:w-auto"
                        >
                            <Link :href="member.user.profile_url">
                                Profil ansehen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <nav
                v-if="members.last_page > 1"
                class="mt-6 flex items-center justify-center gap-2"
                aria-label="Gruppenmitglieder Seiten"
            >
                <Button
                    as-child
                    variant="secondary"
                    :disabled="!members.prev_page_url"
                >
                    <Link
                        v-if="members.prev_page_url"
                        :href="members.prev_page_url"
                    >
                        ← Vorherige
                    </Link>
                    <span v-else>← Vorherige</span>
                </Button>

                <Button
                    as-child
                    variant="secondary"
                    :disabled="!members.next_page_url"
                >
                    <Link
                        v-if="members.next_page_url"
                        :href="members.next_page_url"
                    >
                        Nächste →
                    </Link>
                    <span v-else>Nächste →</span>
                </Button>
            </nav>
        </PageSection>
    </div>
</template>
