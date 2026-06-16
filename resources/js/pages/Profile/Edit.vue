<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ProfileForm = {
    display_name: string;
    bio: string | null;
    region: string | null;
    languages: string;
    interests: string;
    profile_visibility: string;
    interests_visibility: string;
    languages_visibility: string;
    region_visibility: string;
    social_counts_visibility: string;
};

type VisibilityOption = {
    value: string;
    label: string;
};

defineProps<{
    profile: ProfileForm;
    visibilityOptions: VisibilityOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profil bearbeiten',
                href: '/profile/edit',
            },
        ],
    },
});
</script>

<template>
    <Head title="Profil bearbeiten" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Profil bearbeiten"
            description="Passe deine NEAREON-Profildaten und Sichtbarkeit an."
        />

        <PageSection padded>
            <Card>
                <CardContent>
                    <Form
                        action="/profile"
                        method="patch"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="display_name">Anzeigename</Label>
                            <Input
                                id="display_name"
                                name="display_name"
                                type="text"
                                required
                                maxlength="80"
                                autocomplete="name"
                                :default-value="profile.display_name"
                            />
                            <InputError :message="errors.display_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="bio">Kurzinfo</Label>
                            <textarea
                                id="bio"
                                name="bio"
                                maxlength="280"
                                rows="4"
                                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                :default-value="profile.bio ?? ''"
                            />
                            <InputError :message="errors.bio" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="region">Region</Label>
                            <Input
                                id="region"
                                name="region"
                                type="text"
                                maxlength="120"
                                :default-value="profile.region ?? ''"
                            />
                            <InputError :message="errors.region" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="languages">Sprachen</Label>
                            <Input
                                id="languages"
                                name="languages"
                                type="text"
                                :default-value="profile.languages"
                                placeholder="Deutsch, Englisch"
                            />
                            <p class="text-sm text-muted-foreground">
                                Kommagetrennt, maximal 20 Eintraege.
                            </p>
                            <InputError :message="errors.languages" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="interests">Interessen</Label>
                            <Input
                                id="interests"
                                name="interests"
                                type="text"
                                :default-value="profile.interests"
                                placeholder="Musik, Events, Technik"
                            />
                            <p class="text-sm text-muted-foreground">
                                Kommagetrennt, maximal 20 Eintraege.
                            </p>
                            <InputError :message="errors.interests" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="profile_visibility">
                                    Sichtbarkeit Profil
                                </Label>
                                <select
                                    id="profile_visibility"
                                    name="profile_visibility"
                                    :default-value="profile.profile_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in visibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.profile_visibility"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="interests_visibility">
                                    Sichtbarkeit Interessen
                                </Label>
                                <select
                                    id="interests_visibility"
                                    name="interests_visibility"
                                    :default-value="
                                        profile.interests_visibility
                                    "
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in visibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.interests_visibility"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="languages_visibility">
                                    Sichtbarkeit Sprachen
                                </Label>
                                <select
                                    id="languages_visibility"
                                    name="languages_visibility"
                                    :default-value="
                                        profile.languages_visibility
                                    "
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in visibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.languages_visibility"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="region_visibility">
                                    Sichtbarkeit Region
                                </Label>
                                <select
                                    id="region_visibility"
                                    name="region_visibility"
                                    :default-value="profile.region_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in visibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.region_visibility"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="social_counts_visibility">
                                    Sichtbarkeit Social-Zahlen
                                </Label>
                                <select
                                    id="social_counts_visibility"
                                    name="social_counts_visibility"
                                    :default-value="
                                        profile.social_counts_visibility
                                    "
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in visibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.social_counts_visibility"
                                />
                            </div>
                        </div>

                        <div class="flex items-center">
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="update-neareon-profile-button"
                            >
                                <Spinner v-if="processing" />
                                Aenderungen speichern
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
