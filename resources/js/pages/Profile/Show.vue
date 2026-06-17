<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type PublicProfile = {
    username: string;
    isOwnProfile: boolean;
    is_following: boolean;
    is_followed_by: boolean;
    is_mutual: boolean;
    display_name?: string;
    bio?: string | null;
    region?: string | null;
    languages?: string[] | null;
    interests?: string[] | null;
};

const props = defineProps<{
    profile: PublicProfile;
    editProfileHref?: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profil',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head :title="profile.display_name ?? `@${profile.username}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            :title="profile.display_name ?? `@${profile.username}`"
            :description="`@${profile.username}`"
        />

        <PageSection v-if="props.editProfileHref">
            <Button as-child>
                <Link :href="props.editProfileHref">Profil bearbeiten</Link>
            </Button>
        </PageSection>

        <PageSection v-if="!props.profile.isOwnProfile">
            <Form
                v-if="props.profile.is_following"
                :action="`/u/${props.profile.username}/follow`"
                method="delete"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Entfolgen
                </Button>
            </Form>

            <Form
                v-else
                :action="`/u/${props.profile.username}/follow`"
                method="post"
                v-slot="{ processing }"
            >
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Folgen
                </Button>
            </Form>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-4">
                    <p
                        v-if="props.profile.bio"
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        {{ props.profile.bio }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Für dieses Profil sind aktuell keine weiteren Angaben
                        sichtbar.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection
            v-if="
                props.profile.region ||
                props.profile.languages?.length ||
                props.profile.interests?.length
            "
        >
            <div class="grid gap-4 md:grid-cols-3">
                <Card v-if="props.profile.region">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Region</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ props.profile.region }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="props.profile.languages?.length">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Sprachen</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ props.profile.languages.join(', ') }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="props.profile.interests?.length">
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Interessen</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ props.profile.interests.join(', ') }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
