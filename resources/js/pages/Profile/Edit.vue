<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppBackButton from '@/components/AppBackButton.vue';
import BioEmojiField from '@/components/BioEmojiField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import ProfileAvatar from '@/components/ProfileAvatar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ProfileForm = {
    display_name: string;
    bio: string | null;
    profile_photo_url: string | null;
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

type BackLink = {
    href: string;
    label: string;
    source: 'home';
};

const props = defineProps<{
    backLink: BackLink | null;
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

const photoInput = ref<HTMLInputElement | null>(null);
const localPhotoPreview = ref<string | null>(null);
const removeProfilePhoto = ref(false);
const photoPreview = computed(() =>
    removeProfilePhoto.value
        ? null
        : (localPhotoPreview.value ?? props.profile.profile_photo_url),
);
const avatarInitial = computed(() =>
    props.profile.display_name.charAt(0).toUpperCase(),
);

const clearLocalPhotoPreview = () => {
    if (localPhotoPreview.value) {
        URL.revokeObjectURL(localPhotoPreview.value);
        localPhotoPreview.value = null;
    }
};

const selectPhoto = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    clearLocalPhotoPreview();
    removeProfilePhoto.value = false;

    if (file) {
        localPhotoPreview.value = URL.createObjectURL(file);
    }
};

const removePhoto = () => {
    clearLocalPhotoPreview();
    removeProfilePhoto.value = true;

    if (photoInput.value) {
        photoInput.value.value = '';
    }
};

onBeforeUnmount(clearLocalPhotoPreview);

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
        <Button
            v-if="backLink"
            as-child
            variant="secondary"
            class="max-w-full min-w-0 w-fit"
        >
            <Link :href="backLink.href" class="min-w-0 truncate">
                ← {{ backLink.label }}
            </Link>
        </Button>
        <AppBackButton
            v-else
            fallback="/profile"
            label="Zurück zum Profil"
            class="hidden md:inline-flex"
        />

        <PageHeader
            title="Profil bearbeiten"
            description="Passe deinen öffentlichen Auftritt, deine Interessen und deine Sichtbarkeit in der Community an."
        />

        <PageSection padded>
            <Card>
                <CardContent>
                    <Form
                        action="/profile"
                        method="post"
                        enctype="multipart/form-data"
                        class="profile-edit-form space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <input type="hidden" name="_method" value="patch" />

                        <div class="grid gap-3">
                            <div>
                                <h2 class="text-lg font-semibold">
                                    Profilbild
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    Dein Profilbild erscheint auf deinem Profil,
                                    in Discover, Kontakten und Nachrichten. JPG,
                                    JPEG, PNG oder WEBP bis maximal 5 MB.
                                </p>
                            </div>

                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-center"
                            >
                                <ProfileAvatar
                                    :photo-url="photoPreview"
                                    :alt="profile.display_name"
                                    :fallback="avatarInitial"
                                    class="size-20 shrink-0"
                                    fallback-class="text-2xl"
                                />

                                <div class="flex flex-col gap-2">
                                    <Label
                                        for="profile_photo"
                                        class="cursor-pointer"
                                    >
                                        Bild auswählen
                                    </Label>
                                    <Input
                                        id="profile_photo"
                                        ref="photoInput"
                                        name="profile_photo"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        class="max-w-md"
                                        @change="selectPhoto"
                                    />
                                    <input
                                        type="hidden"
                                        name="remove_profile_photo"
                                        :value="removeProfilePhoto ? '1' : '0'"
                                    />
                                    <Button
                                        v-if="photoPreview"
                                        type="button"
                                        variant="secondary"
                                        class="w-fit"
                                        @click="removePhoto"
                                    >
                                        Bild entfernen
                                    </Button>
                                </div>
                            </div>

                            <InputError :message="errors.profile_photo" />
                        </div>

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
                            <p class="text-sm text-muted-foreground">
                                Dieser Name ist in der Community sichtbar. Dein
                                Account-Name in den Einstellungen bleibt davon
                                getrennt.
                            </p>
                            <InputError :message="errors.display_name" />
                        </div>

                        <div>
                            <BioEmojiField
                                :initial-value="profile.bio"
                                placeholder="Erzähle kurz, wer du bist oder wonach du suchst."
                            />
                            <p class="mt-2 text-sm text-muted-foreground">
                                Maximal 280 Zeichen. Zeilenumbrüche und Emojis
                                bleiben auf deinem Profil erhalten.
                            </p>
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
                            <p class="text-sm text-muted-foreground">
                                Optional. Die Region hilft anderen Mitgliedern,
                                passende Profile in Discover zu finden.
                            </p>
                            <InputError :message="errors.region" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Sprachen</Label>
                            <p class="text-sm text-muted-foreground">
                                Wähle die Sprachen aus, die andere auf deinem
                                Profil sehen dürfen.
                            </p>
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
                                Maximal 20 Sprachen. Gewählte inaktive Optionen
                                bleiben sichtbar, bis du sie entfernst.
                            </p>
                            <InputError :message="errors.languages" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Interessen</Label>
                            <p class="text-sm text-muted-foreground">
                                Interessen helfen bei gemeinsamen Treffern in
                                Discover und auf Profilseiten.
                            </p>
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
                                Maximal 20 Interessen. Gewählte inaktive
                                Optionen bleiben sichtbar, bis du sie entfernst.
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
                                {{
                                    processing
                                        ? 'Wird gespeichert...'
                                        : 'Änderungen speichern'
                                }}
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
