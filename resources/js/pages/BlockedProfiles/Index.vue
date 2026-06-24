<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import CommunityBackLink from '@/components/CommunityBackLink.vue';
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
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    formatContactRelativeTime,
    formatContactRelativeTimeTitle,
} from '@/lib/contactRelativeTime';
import { unblockUserAction } from '@/lib/relationshipActions';

type BlockedProfile = {
    blocked_at: string;
    display_name: string;
    profile_photo_url: string | null;
    username: string;
};

defineProps<{
    blockedProfiles: BlockedProfile[];
}>();

const unblockingUsername = ref<string | null>(null);

const setUnblockDialogOpen = (username: string, open: boolean) => {
    unblockingUsername.value = open ? username : null;
};

const avatarInitial = (profile: BlockedProfile) =>
    profile.display_name.charAt(0).toUpperCase();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Blockierte Profile',
                href: '/blocked-profiles',
            },
        ],
    },
});
</script>

<template>
    <Head title="Blockierte Profile" />

    <div
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <CommunityBackLink />

        <PageHeader
            title="Blockierte Profile"
            description="Hier kannst du von dir blockierte Profile verwalten."
        />

        <PageSection v-if="blockedProfiles.length === 0">
            <Card
                class="border-border/80 bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent
                    class="flex flex-col items-center gap-4 px-6 py-10 text-center"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-full border border-primary/25 bg-primary/10 text-primary"
                    >
                        <ShieldCheck class="size-6" aria-hidden="true" />
                    </div>
                    <div class="max-w-md space-y-1.5">
                        <h2 class="font-semibold tracking-tight">
                            Keine blockierten Profile
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Du hast derzeit keine Profile blockiert. Wenn du
                            jemanden blockierst, kannst du die Blockierung hier
                            später wieder aufheben.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="profile in blockedProfiles"
                    :key="profile.username"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-3 p-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <ProfileAvatar
                                :photo-url="profile.profile_photo_url"
                                :alt="profile.display_name"
                                :fallback="avatarInitial(profile)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ profile.display_name }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ profile.username }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Badge
                                variant="secondary"
                                class="border border-destructive/30 bg-destructive/10 text-destructive dark:bg-destructive/15"
                            >
                                Blockiert
                            </Badge>
                            <p class="text-xs text-muted-foreground">
                                Blockiert:
                                <time
                                    :datetime="profile.blocked_at"
                                    :title="
                                        formatContactRelativeTimeTitle(
                                            profile.blocked_at,
                                        )
                                    "
                                >
                                    {{
                                        formatContactRelativeTime(
                                            profile.blocked_at,
                                        )
                                    }}
                                </time>
                            </p>
                        </div>

                        <div class="mt-auto pt-1">
                            <Button
                                type="button"
                                variant="secondary"
                                class="w-full"
                                @click="unblockingUsername = profile.username"
                            >
                                {{ unblockUserAction(profile.username).label }}
                            </Button>
                        </div>

                        <Dialog
                            :open="unblockingUsername === profile.username"
                            @update:open="
                                setUnblockDialogOpen(profile.username, $event)
                            "
                        >
                            <DialogContent>
                                <Form
                                    :action="
                                        unblockUserAction(profile.username)
                                            .action
                                    "
                                    :method="
                                        unblockUserAction(profile.username)
                                            .method
                                    "
                                    :options="{ preserveScroll: true }"
                                    v-slot="{ processing }"
                                    class="space-y-6"
                                    @success="unblockingUsername = null"
                                >
                                    <DialogHeader class="space-y-3">
                                        <DialogTitle>
                                            Blockierung aufheben?
                                        </DialogTitle>
                                        <DialogDescription>
                                            Die Blockierung von
                                            {{ profile.display_name }} wird
                                            entfernt. Ob anschließend
                                            Interaktionen möglich sind, richtet
                                            sich nach euren Profileinstellungen.
                                            Frühere Follow- und
                                            Kontaktbeziehungen werden nicht
                                            automatisch wiederhergestellt.
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
                                            variant="destructive"
                                            :disabled="processing"
                                        >
                                            <Spinner v-if="processing" />
                                            {{
                                                unblockUserAction(
                                                    profile.username,
                                                ).label
                                            }}
                                        </Button>
                                    </DialogFooter>
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
