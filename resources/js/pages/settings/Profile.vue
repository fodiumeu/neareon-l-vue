<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profileinstellungen',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profileinstellungen" />

    <h1 class="sr-only">Profileinstellungen</h1>

    <PageSection>
        <Heading
            variant="small"
            title="Profilinformationen"
            description="Verwalte die Zugangsdaten deines Accounts. Dein Community-Profil bearbeitest du separat."
        />

        <div
            class="mb-6 rounded-lg border border-border bg-card/70 px-4 py-3 text-sm text-muted-foreground"
        >
            <p>
                Account-Name und E-Mail gehören zur Anmeldung. Deinen
                sichtbaren NEAREON-Anzeigenamen, Bio, Profilbild, Sprachen und
                Interessen bearbeitest du im Community-Profil.
            </p>
            <Link
                href="/profile/edit"
                class="mt-3 inline-flex text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
            >
                NEAREON-Profil bearbeiten
            </Link>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Account-Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Account-Name"
                />
                <p class="text-sm text-muted-foreground">
                    Der Account-Name wird für deine Anmeldung und
                    Kontoverwaltung genutzt. Auf deinem NEAREON-Profil erscheint
                    dein Anzeigename.
                </p>
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="E-Mail-Adresse"
                />
                <p class="text-sm text-muted-foreground">
                    Diese Adresse nutzt NEAREON für Anmeldung und
                    Account-Sicherheit.
                </p>
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Deine E-Mail-Adresse ist noch nicht bestätigt.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Klicke hier, um die Bestätigungs-E-Mail erneut zu
                        senden.
                    </Link>
                </p>

                <div
                    v-if="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse
                    gesendet.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button">
                    <Spinner v-if="processing" />
                    {{ processing ? 'Wird gespeichert...' : 'Speichern' }}
                </Button>
            </div>
        </Form>
    </PageSection>

    <DeleteUser />
</template>
