<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import BioEmojiField from '@/components/BioEmojiField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profil einrichten',
                href: '/onboarding/details',
            },
        ],
    },
});
</script>

<template>
    <Head title="Profil einrichten" />

    <div
        class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <div class="space-y-2">
            <p
                class="text-xs font-semibold tracking-wide text-primary uppercase"
            >
                Schritt 1 von 3
            </p>
            <PageHeader
                title="Profil einrichten"
                description="Lege dein NEAREON-Profil an, damit du später gefunden werden und die Community-Funktionen nutzen kannst."
            />
        </div>

        <PageSection padded>
            <Card
                class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent>
                    <Form
                        action="/onboarding/details"
                        method="post"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="username">Benutzername</Label>
                            <Input
                                id="username"
                                name="username"
                                type="text"
                                required
                                autofocus
                                autocomplete="username"
                                maxlength="30"
                                pattern="[a-z0-9_-]{3,30}"
                                placeholder="dein_name"
                            />
                            <p class="text-sm text-muted-foreground">
                                3 bis 30 Zeichen, nur Kleinbuchstaben, Zahlen,
                                Bindestrich und Unterstrich.
                            </p>
                            <InputError :message="errors.username" />
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
                                placeholder="Dein Name"
                            />
                            <InputError :message="errors.display_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="region">Region</Label>
                            <Input
                                id="region"
                                name="region"
                                type="text"
                                maxlength="120"
                                placeholder="z. B. Berlin"
                            />
                            <InputError :message="errors.region" />
                        </div>

                        <div>
                            <BioEmojiField
                                placeholder="Ein kurzer Satz über dich"
                            />
                            <InputError :message="errors.bio" />
                        </div>

                        <div class="flex items-center">
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="processing"
                                data-test="create-profile-button"
                            >
                                <Spinner v-if="processing" />
                                Weiter
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
