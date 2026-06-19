<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type BlockedProfile = {
    blocked_at: string;
    display_name: string;
    username: string;
};

defineProps<{
    blockedProfiles: BlockedProfile[];
}>();

const avatarInitial = (profile: BlockedProfile) =>
    profile.display_name.charAt(0).toUpperCase();

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

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
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Blockierte Profile"
            description="Hier kannst du von dir blockierte Profile verwalten."
        />

        <PageSection v-if="blockedProfiles.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit keine Profile blockiert.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="profile in blockedProfiles"
                    :key="profile.username"
                    class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <Avatar
                                class="size-12 shrink-0 border border-primary/25"
                            >
                                <AvatarFallback
                                    class="bg-primary/15 text-base font-semibold text-primary"
                                >
                                    {{ avatarInitial(profile) }}
                                </AvatarFallback>
                            </Avatar>

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

                        <p class="text-xs text-muted-foreground">
                            Blockiert am {{ formatDate(profile.blocked_at) }}
                        </p>

                        <Form
                            :action="`/u/${profile.username}/block`"
                            method="delete"
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                            class="mt-auto"
                        >
                            <Button
                                type="submit"
                                variant="secondary"
                                :disabled="processing"
                                class="w-full"
                            >
                                <Spinner v-if="processing" />
                                Blockierung aufheben
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
