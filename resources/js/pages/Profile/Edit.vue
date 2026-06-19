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
    languages: string[];
    interests: string[];
    profile_visibility: string;
    follow_permission: string;
    contact_permission: string;
    message_permission: string;
    online_status_visibility: string;
    interests_visibility: string;
    languages_visibility: string;
    region_visibility: string;
    social_counts_visibility: string;
};

type VisibilityOption = {
    value: string;
    label: string;
};

type ProfileOption = {
    value: string;
    label: string;
    is_active: boolean;
};

defineProps<{
    profile: ProfileForm;
    languageOptions: ProfileOption[];
    interestOptions: ProfileOption[];
    fieldVisibilityOptions: VisibilityOption[];
    profileVisibilityOptions: VisibilityOption[];
    followPermissionOptions: VisibilityOption[];
    contactPermissionOptions: VisibilityOption[];
    messagePermissionOptions: VisibilityOption[];
    onlineStatusVisibilityOptions: VisibilityOption[];
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
                            <Label for="bio">Bio</Label>
                            <textarea
                                id="bio"
                                name="bio"
                                maxlength="280"
                                rows="4"
                                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                :value="profile.bio ?? ''"
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
                            <Label>Sprachen</Label>
                            <div
                                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <Label
                                    v-for="option in languageOptions"
                                    :key="option.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-md border border-input bg-background/70 px-3 py-2 text-sm transition-colors hover:border-ring/70 hover:bg-accent/70"
                                >
                                    <input
                                        type="checkbox"
                                        name="languages[]"
                                        :value="option.value"
                                        :checked="
                                            profile.languages.includes(
                                                option.value,
                                            )
                                        "
                                        class="size-4 rounded border-input accent-primary"
                                    />
                                    <span class="leading-5">
                                        {{ option.label }}
                                        <span
                                            v-if="!option.is_active"
                                            class="text-muted-foreground"
                                        >
                                            (inaktiv)
                                        </span>
                                    </span>
                                </Label>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Wähle maximal 20 Sprachen aus.
                            </p>
                            <InputError :message="errors.languages" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Interessen</Label>
                            <div
                                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <Label
                                    v-for="option in interestOptions"
                                    :key="option.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-md border border-input bg-background/70 px-3 py-2 text-sm transition-colors hover:border-ring/70 hover:bg-accent/70"
                                >
                                    <input
                                        type="checkbox"
                                        name="interests[]"
                                        :value="option.value"
                                        :checked="
                                            profile.interests.includes(
                                                option.value,
                                            )
                                        "
                                        class="size-4 rounded border-input accent-primary"
                                    />
                                    <span class="leading-5">
                                        {{ option.label }}
                                        <span
                                            v-if="!option.is_active"
                                            class="text-muted-foreground"
                                        >
                                            (inaktiv)
                                        </span>
                                    </span>
                                </Label>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Wähle maximal 20 Interessen aus.
                            </p>
                            <InputError :message="errors.interests" />
                        </div>

                        <div class="space-y-2 border-t border-border pt-6">
                            <h2 class="text-lg font-semibold">Privatsphäre</h2>
                            <p class="text-sm text-muted-foreground">
                                Lege fest, wer dein Profil sehen und mit dir
                                interagieren darf.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="profile_visibility">
                                    Wer darf dein Profil sehen?
                                </Label>
                                <select
                                    id="profile_visibility"
                                    name="profile_visibility"
                                    :value="profile.profile_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in profileVisibilityOptions"
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
                                <Label for="follow_permission">
                                    Wer darf dir folgen?
                                </Label>
                                <select
                                    id="follow_permission"
                                    name="follow_permission"
                                    :value="profile.follow_permission"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in followPermissionOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.follow_permission"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="contact_permission">
                                    Wer darf Kontaktanfragen senden?
                                </Label>
                                <select
                                    id="contact_permission"
                                    name="contact_permission"
                                    :value="profile.contact_permission"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in contactPermissionOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.contact_permission"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="message_permission">
                                    Wer darf Nachrichten senden?
                                </Label>
                                <select
                                    id="message_permission"
                                    name="message_permission"
                                    :value="profile.message_permission"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in messagePermissionOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.message_permission"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="online_status_visibility">
                                    Wer darf später deinen Online-Status sehen?
                                </Label>
                                <select
                                    id="online_status_visibility"
                                    name="online_status_visibility"
                                    :value="profile.online_status_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in onlineStatusVisibilityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="errors.online_status_visibility"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Diese Einstellung bereitet die spätere
                                    Funktion nur vor. Aktuell wird kein
                                    Online-Status angezeigt.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="interests_visibility">
                                    Sichtbarkeit Interessen
                                </Label>
                                <select
                                    id="interests_visibility"
                                    name="interests_visibility"
                                    :value="profile.interests_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in fieldVisibilityOptions"
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
                                    :value="profile.languages_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in fieldVisibilityOptions"
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
                                    :value="profile.region_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in fieldVisibilityOptions"
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
                                    :value="profile.social_counts_visibility"
                                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option
                                        v-for="option in fieldVisibilityOptions"
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
                                Änderungen speichern
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
