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

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Profil einrichten"
            description="Lege dein NEAREON-Profil an, damit du spaeter gefunden werden und die Community-Funktionen nutzen kannst."
        />

        <PageSection padded>
            <Card>
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

                        <div class="grid gap-2">
                            <Label for="bio">Bio</Label>
                            <textarea
                                id="bio"
                                name="bio"
                                maxlength="280"
                                rows="4"
                                class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                placeholder="Ein kurzer Satz ueber dich"
                            />
                            <InputError :message="errors.bio" />
                        </div>

                        <div class="flex items-center">
                            <Button
                                type="submit"
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
