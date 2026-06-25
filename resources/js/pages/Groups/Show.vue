<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBackButton from '@/components/AppBackButton.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type GroupMemberPreview = {
    id: number;
    role_label: string;
    user: {
        name: string;
        username?: string | null;
        profile_photo_url?: string | null;
    };
};

type GroupDetail = {
    id: number;
    name: string;
    description?: string | null;
    region?: string | null;
    visibility: 'public' | 'request' | 'private';
    visibility_label: string;
    member_count: number;
    owner?: {
        name: string;
        username?: string | null;
    } | null;
    membership?: {
        role_label: string;
        status_label: string;
    } | null;
    members: GroupMemberPreview[];
};

defineProps<{
    group: GroupDetail;
}>();

const visibilityBadgeClass = (visibility: GroupDetail['visibility']) =>
    visibility === 'private'
        ? 'border-border bg-background/70 text-muted-foreground dark:bg-input/30'
        : visibility === 'request'
          ? 'border-primary/30 bg-primary/10 text-primary'
          : 'border-primary/30 bg-primary/10 text-primary';

const avatarInitial = (name: string) => name.charAt(0).toUpperCase();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gruppen',
                href: '/groups',
            },
        ],
    },
});
</script>

<template>
    <Head :title="group.name" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <AppBackButton
            fallback="/groups"
            label="Zurück zu Gruppen"
            class="hidden md:inline-flex"
        />

        <PageHeader
            :title="group.name"
            description="Gruppen-Foundation für regionale und thematische Community-Bereiche."
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent class="space-y-5 p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    variant="outline"
                                    :class="visibilityBadgeClass(group.visibility)"
                                >
                                    {{ group.visibility_label }}
                                </Badge>
                                <Badge v-if="group.region" variant="secondary">
                                    {{ group.region }}
                                </Badge>
                                <Badge
                                    v-if="group.membership"
                                    variant="outline"
                                    class="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {{ group.membership.role_label }}
                                </Badge>
                            </div>

                            <p
                                v-if="group.description"
                                class="max-w-3xl text-sm leading-6 whitespace-pre-wrap text-muted-foreground sm:text-base"
                            >
                                {{ group.description }}
                            </p>
                            <p
                                v-else
                                class="rounded-md border border-dashed border-border/80 bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
                            >
                                Diese Gruppe hat noch keine Beschreibung.
                            </p>
                        </div>

                        <div
                            class="grid gap-3 rounded-lg border border-border bg-background/60 p-4 text-sm dark:bg-input/20 lg:w-64"
                        >
                            <div>
                                <p class="text-muted-foreground">Mitglieder</p>
                                <p class="text-lg font-semibold">
                                    {{ group.member_count }}
                                </p>
                            </div>
                            <div v-if="group.owner">
                                <p class="text-muted-foreground">Owner</p>
                                <p class="font-medium">{{ group.owner.name }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <Card>
                <CardContent class="space-y-4 p-5">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">
                            Neueste Mitglieder
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Ein kleiner Ausblick auf die aktiven Mitglieder
                            dieser Gruppe.
                        </p>
                    </div>

                    <div v-if="group.members.length" class="grid gap-3 md:grid-cols-2">
                        <div
                            v-for="member in group.members"
                            :key="member.id"
                            class="flex items-center gap-3 rounded-lg border border-border bg-background/60 px-3 py-2 dark:bg-input/20"
                        >
                            <ProfileAvatar
                                :photo-url="member.user.profile_photo_url"
                                :alt="member.user.name"
                                :fallback="avatarInitial(member.user.name)"
                                class="size-10 shrink-0"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ member.user.name }}
                                </p>
                                <p
                                    v-if="member.user.username"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ member.user.username }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ member.role_label }}
                            </Badge>
                        </div>
                    </div>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        Für diese Gruppe werden aktuell keine Mitglieder
                        angezeigt.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <Card>
                <CardContent class="p-5">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Weitere Gruppenfunktionen wie Beitritt, Chat und Events
                        folgen in späteren Modulen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
