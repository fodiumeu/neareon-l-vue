<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Contact = {
    display_name: string;
    username: string;
};

defineProps<{
    contacts: Contact[];
}>();

const avatarInitial = (contact: Contact) =>
    contact.display_name.charAt(0).toUpperCase();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kontakte',
                href: '/contacts',
            },
        ],
    },
});
</script>

<template>
    <Head title="Kontakte" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Kontakte"
            description="Hier siehst du alle Benutzer, mit denen du gegenseitig verbunden bist."
        />

        <PageSection v-if="contacts.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit noch keine Kontakte.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="contact in contacts"
                    :key="contact.username"
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
                                    {{ avatarInitial(contact) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ contact.display_name }}
                                </h2>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contact.username }}
                                </p>
                            </div>
                        </div>

                        <Button
                            as-child
                            variant="secondary"
                            class="mt-auto w-full"
                        >
                            <Link :href="`/u/${contact.username}`">
                                Profil ansehen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
