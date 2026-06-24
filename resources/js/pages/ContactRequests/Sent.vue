<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CommunityBackLink from '@/components/CommunityBackLink.vue';
import ContactRequestStatusBadge from '@/components/ContactRequestStatusBadge.vue';
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
import type { ContactRequestStatus } from '@/types';

type SentContactRequest = {
    common_interests: string[];
    common_languages: string[];
    id: number;
    message: string | null;
    status: ContactRequestStatus;
    created_at: string;
    receiver: {
        display_name: string;
        profile_photo_url: string | null;
        username: string | null;
    };
};

defineProps<{
    contactRequests: SentContactRequest[];
}>();

const avatarInitial = (contactRequest: SentContactRequest) =>
    contactRequest.receiver.display_name.charAt(0).toUpperCase();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gesendete Kontaktanfragen',
                href: '/contact-requests/sent',
            },
        ],
    },
});
</script>

<template>
    <Head title="Gesendete Kontaktanfragen" />

    <div
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <CommunityBackLink />

        <PageHeader
            title="Gesendete Kontaktanfragen"
            description="Hier siehst du deine gesendeten Kontaktanfragen und ihren aktuellen Status."
        />

        <PageSection v-if="contactRequests.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit keine gesendeten Kontaktanfragen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-else>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card
                    v-for="contactRequest in contactRequests"
                    :key="contactRequest.id"
                    class="h-full border-border/80 bg-card/95 shadow-md shadow-black/5 transition-[border-color,box-shadow,transform] duration-200 motion-reduce:transition-none md:hover:-translate-y-0.5 md:hover:border-primary/35 md:hover:shadow-lg md:hover:shadow-primary/10 dark:shadow-black/25"
                >
                    <CardContent class="flex h-full flex-col gap-3 p-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <ProfileAvatar
                                :photo-url="
                                    contactRequest.receiver.profile_photo_url
                                "
                                :alt="contactRequest.receiver.display_name"
                                :fallback="avatarInitial(contactRequest)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ contactRequest.receiver.display_name }}
                                </h2>
                                <p
                                    v-if="contactRequest.receiver.username"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contactRequest.receiver.username }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <ContactRequestStatusBadge
                                :status="contactRequest.status"
                            />
                            <p class="text-xs text-muted-foreground">
                                Gesendet:
                                <time
                                    :datetime="contactRequest.created_at"
                                    :title="
                                        formatContactRelativeTimeTitle(
                                            contactRequest.created_at,
                                        )
                                    "
                                >
                                    {{
                                        formatContactRelativeTime(
                                            contactRequest.created_at,
                                        )
                                    }}
                                </time>
                            </p>
                        </div>

                        <p
                            v-if="contactRequest.message"
                            class="rounded-md border border-border bg-background/60 px-4 py-3 text-sm leading-6 whitespace-pre-wrap text-foreground dark:bg-input/20"
                        >
                            {{ contactRequest.message }}
                        </p>

                        <div
                            v-if="contactRequest.common_languages.length"
                            class="space-y-2"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Gemeinsame Sprachen
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="language in contactRequest.common_languages.slice(
                                        0,
                                        2,
                                    )"
                                    :key="language"
                                    variant="secondary"
                                >
                                    {{ language }}
                                </Badge>
                                <Badge
                                    v-if="
                                        contactRequest.common_languages.length >
                                        2
                                    "
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    +{{
                                        contactRequest.common_languages.length -
                                        2
                                    }}
                                    weitere
                                </Badge>
                            </div>
                        </div>

                        <div
                            v-if="contactRequest.common_interests.length"
                            class="space-y-2"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Gemeinsame Interessen
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="interest in contactRequest.common_interests.slice(
                                        0,
                                        3,
                                    )"
                                    :key="interest"
                                    variant="outline"
                                    class="border-primary/30 bg-primary/10"
                                >
                                    {{ interest }}
                                </Badge>
                                <Badge
                                    v-if="
                                        contactRequest.common_interests.length >
                                        3
                                    "
                                    variant="outline"
                                    class="text-muted-foreground"
                                >
                                    +{{
                                        contactRequest.common_interests.length -
                                        3
                                    }}
                                    weitere
                                </Badge>
                            </div>
                        </div>

                        <div class="mt-auto pt-1">
                            <Button
                                v-if="contactRequest.receiver.username"
                                as-child
                                variant="secondary"
                                class="w-full"
                            >
                                <Link
                                    :href="
                                        profileUrl(
                                            contactRequest.receiver.username,
                                        )
                                    "
                                >
                                    Profil ansehen
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
