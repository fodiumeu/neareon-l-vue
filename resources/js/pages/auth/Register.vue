<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Account erstellen',
        description: 'Gib deine Daten ein, um deinen Account zu erstellen',
    },
});
</script>

<template>
    <Head title="Registrieren" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="2"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Passwort"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Passwort bestaetigen</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Passwort bestaetigen"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="4"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Account erstellen
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Du hast bereits einen Account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="5"
                >Anmelden</TextLink
            >
        </div>
    </Form>
</template>
