<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    formatContactRelativeTime,
    formatContactRelativeTimeTitle,
} from '@/lib/contactRelativeTime';
import {
    acceptContactRequestAction,
    profileUrl,
    rejectContactRequestAction,
} from '@/lib/relationshipActions';

type ContactRequest = {
    common_interests: string[];
    common_languages: string[];
    id: number;
    message: string | null;
    created_at: string;
    sender: {
        display_name: string;
        profile_photo_url: string | null;
        username: string | null;
    };
};

defineProps<{
    contactRequests: ContactRequest[];
}>();

const avatarInitial = (contactRequest: ContactRequest) =>
    contactRequest.sender.display_name.charAt(0).toUpperCase();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kontaktanfragen',
                href: '/contact-requests',
            },
        ],
    },
});
</script>

<template>
    <Head title="Kontaktanfragen" />

    <div
        class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            title="Kontaktanfragen"
            description="Hier siehst du offene Kontaktanfragen, die du erhalten hast. Wenn du eine Anfrage annimmst, folgt ihr euch gegenseitig und werdet Kontakte."
        />

        <PageSection v-if="contactRequests.length === 0">
            <Card
                class="bg-card/95 shadow-md shadow-black/5 dark:shadow-black/25"
            >
                <CardContent class="text-center sm:text-left">
                    <p class="text-sm leading-6 text-muted-foreground">
                        Du hast derzeit keine offenen Kontaktanfragen.
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
                                    contactRequest.sender.profile_photo_url
                                "
                                :alt="contactRequest.sender.display_name"
                                :fallback="avatarInitial(contactRequest)"
                                class="size-16 shrink-0 shadow-sm"
                                fallback-class="text-xl"
                            />

                            <div class="min-w-0 flex-1 space-y-1">
                                <h2
                                    class="truncate text-base font-semibold tracking-tight"
                                >
                                    {{ contactRequest.sender.display_name }}
                                </h2>
                                <p
                                    v-if="contactRequest.sender.username"
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    @{{ contactRequest.sender.username }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Eingegangen:
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

                        <div class="mt-auto grid gap-2 pt-1">
                            <Form
                                :action="
                                    acceptContactRequestAction(
                                        contactRequest.id,
                                    ).action
                                "
                                :method="
                                    acceptContactRequestAction(
                                        contactRequest.id,
                                    ).method
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="w-full"
                                >
                                    <Spinner v-if="processing" />
                                    {{
                                        acceptContactRequestAction(
                                            contactRequest.id,
                                        ).label
                                    }}
                                </Button>
                            </Form>

                            <Form
                                :action="
                                    rejectContactRequestAction(
                                        contactRequest.id,
                                    ).action
                                "
                                :method="
                                    rejectContactRequestAction(
                                        contactRequest.id,
                                    ).method
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    :disabled="processing"
                                    class="w-full"
                                >
                                    <Spinner v-if="processing" />
                                    {{
                                        rejectContactRequestAction(
                                            contactRequest.id,
                                        ).label
                                    }}
                                </Button>
                            </Form>

                            <Button
                                v-if="contactRequest.sender.username"
                                as-child
                                variant="secondary"
                                class="w-full"
                            >
                                <Link
                                    :href="
                                        profileUrl(
                                            contactRequest.sender.username,
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
