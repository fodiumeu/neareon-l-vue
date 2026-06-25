<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type GroupMemberPreview = {
    id: number;
    role_label: string;
    user: {
        name: string;
        username?: string | null;
        profile_photo_url?: string | null;
    };
};

type PendingGroupRequest = {
    id: number;
    requested_at?: string | null;
    user: {
        name: string;
        username?: string | null;
        profile_photo_url?: string | null;
    };
    accept_url: string;
    decline_url: string;
    profile_url?: string | null;
};

type GroupDetail = {
    id: number;
    name: string;
    description?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    visibility: 'public' | 'request' | 'private';
    visibility_label: string;
    member_count: number;
    back_label: string;
    back_source: 'groups' | 'my-groups';
    back_url: string;
    can_edit: boolean;
    can_join: boolean;
    can_leave: boolean;
    category: {
        id: number;
        slug: string;
        label: string;
    } | null;
    edit_url: string;
    join_label?: string | null;
    join_url?: string | null;
    leave_label?: string | null;
    leave_url?: string | null;
    owner?: {
        name: string;
        username?: string | null;
    } | null;
    membership?: {
        role_label: string;
        status_label: string;
    } | null;
    members: GroupMemberPreview[];
    pending_requests: PendingGroupRequest[];
    viewer_membership_status?: string | null;
    viewer_role?: string | null;
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

const locationLabel = (group: GroupDetail) =>
    [group.postal_code, group.region].filter(Boolean).join(' ');

const formatRequestTime = (value?: string | null) => {
    if (!value) {
        return 'Zeitpunkt unbekannt';
    }

    return new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

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
        <Button
            as-child
            variant="secondary"
            class="hidden w-fit md:inline-flex"
        >
            <Link :href="group.back_url">← {{ group.back_label }}</Link>
        </Button>

        <PageHeader
            :title="group.name"
            description="Gruppen-Foundation für regionale und thematische Community-Bereiche."
        />

        <div v-if="group.can_edit" class="flex justify-start sm:justify-end">
            <Button as-child variant="secondary" class="w-full sm:w-auto">
                <Link :href="group.edit_url">Gruppe bearbeiten</Link>
            </Button>
        </div>

        <PageSection
            v-if="group.can_join || group.viewer_membership_status === 'pending' || group.viewer_membership_status === 'active'"
        >
            <Card>
                <CardContent
                    class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">
                            Gruppenstatus
                        </h2>
                        <p
                            v-if="group.viewer_membership_status === 'pending'"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Deine Beitrittsanfrage wartet auf Bestätigung.
                        </p>
                        <p
                            v-else-if="group.viewer_membership_status === 'active'"
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Du bist Mitglied dieser Gruppe.
                        </p>
                        <p
                            v-else
                            class="text-sm leading-6 text-muted-foreground"
                        >
                            Tritt dieser Gruppe bei oder sende eine
                            Beitrittsanfrage.
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 sm:items-end"
                    >
                        <Form
                            v-if="group.can_join && group.join_url"
                            :action="group.join_url"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <input
                                type="hidden"
                                name="return_to"
                                :value="group.back_source"
                            />
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="processing"
                            >
                                {{
                                    processing
                                        ? 'Wird verarbeitet...'
                                        : group.join_label
                                }}
                            </Button>
                        </Form>
                        <Badge
                            v-else-if="group.viewer_membership_status === 'pending'"
                            variant="outline"
                            class="w-fit border-primary/30 bg-primary/10 text-primary"
                        >
                            Anfrage gesendet
                        </Badge>
                        <Badge
                            v-else-if="group.viewer_membership_status === 'active'"
                            variant="outline"
                            class="w-fit border-primary/30 bg-primary/10 text-primary"
                        >
                            {{ group.membership?.role_label ?? 'Mitglied' }}
                        </Badge>

                        <Dialog
                            v-if="group.can_leave && group.leave_url && group.viewer_membership_status === 'active'"
                        >
                            <DialogTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10 sm:w-auto"
                                >
                                    {{ group.leave_label }}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <Form
                                    :action="group.leave_url"
                                    method="delete"
                                    v-slot="{ processing }"
                                    class="space-y-6"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Gruppe verlassen?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Du verlässt diese Gruppe und sie
                                            wird nicht mehr unter „Meine
                                            Gruppen“ angezeigt.
                                        </DialogDescription>
                                    </DialogHeader>

                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                :disabled="processing"
                                            >
                                                Abbrechen
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            class="border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10"
                                            :disabled="processing"
                                        >
                                            {{
                                                processing
                                                    ? 'Wird verarbeitet...'
                                                    : 'Gruppe verlassen'
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                        <Form
                            v-else-if="group.can_leave && group.leave_url && group.viewer_membership_status === 'pending'"
                            :action="group.leave_url"
                            method="delete"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                class="w-full border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10 sm:w-auto"
                                :disabled="processing"
                            >
                                    {{
                                        processing
                                            ? 'Wird verarbeitet...'
                                            : (group.leave_label ??
                                                'Anfrage zurückziehen')
                                    }}
                                </Button>
                            </Form>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

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
                                    v-if="group.postal_code"
                                    variant="secondary"
                                >
                                    PLZ {{ group.postal_code }}
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

                            <div
                                v-if="group.category"
                                class="space-y-2"
                            >
                                <p
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Kategorie
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                    >
                                        {{ group.category.label }}
                                    </span>
                                </div>
                            </div>
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
                            <div v-if="group.region || group.postal_code">
                                <p class="text-muted-foreground">Standort</p>
                                <p class="font-medium">
                                    {{ locationLabel(group) }}
                                </p>
                                <p
                                    v-if="group.country_code"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ group.country_code }}
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

        <PageSection v-if="group.can_edit">
            <Card>
                <CardContent class="space-y-4 p-5">
                    <div class="space-y-1">
                        <h2 class="text-base font-semibold">
                            Beitrittsanfragen
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Diese Mitglieder möchten deiner Gruppe beitreten.
                        </p>
                    </div>

                    <div
                        v-if="group.pending_requests.length"
                        class="grid gap-3"
                    >
                        <div
                            v-for="request in group.pending_requests"
                            :key="request.id"
                            class="flex flex-col gap-3 rounded-lg border border-border bg-background/60 p-3 dark:bg-input/20 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <ProfileAvatar
                                    :photo-url="request.user.profile_photo_url"
                                    :alt="request.user.name"
                                    :fallback="avatarInitial(request.user.name)"
                                    class="size-11 shrink-0"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ request.user.name }}
                                    </p>
                                    <p
                                        v-if="request.user.username"
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        @{{ request.user.username }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Angefragt am
                                        {{
                                            formatRequestTime(
                                                request.requested_at,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center"
                            >
                                <Form
                                    :action="request.accept_url"
                                    method="patch"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        class="w-full sm:w-auto"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Wird angenommen...'
                                                : 'Annehmen'
                                        }}
                                    </Button>
                                </Form>
                                <Form
                                    :action="request.decline_url"
                                    method="delete"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        class="w-full border-destructive/30 text-destructive hover:border-destructive/45 hover:bg-destructive/10 sm:w-auto"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Wird abgelehnt...'
                                                : 'Ablehnen'
                                        }}
                                    </Button>
                                </Form>
                                <Button
                                    v-if="request.profile_url"
                                    as-child
                                    variant="secondary"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                >
                                    <Link :href="request.profile_url">
                                        Profil ansehen
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        Aktuell liegen keine Beitrittsanfragen vor.
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
