<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    interests: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Deine Interessen',
                href: '/onboarding/interests',
            },
        ],
    },
});
</script>

<template>
    <Head title="Deine Interessen" />

    <div
        class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <div class="space-y-2">
            <p
                class="text-xs font-semibold tracking-wide text-primary uppercase"
            >
                Schritt 2 von 3
            </p>
            <PageHeader
                title="Deine Interessen"
                description="Wähle Themen aus, die zu dir passen."
            />
        </div>

        <PageSection padded>
            <Card
                class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent>
                    <Form
                        action="/onboarding/interests"
                        method="post"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Label
                                v-for="interest in interests"
                                :key="interest"
                                class="flex cursor-pointer items-center gap-3 rounded-md border border-input bg-background/70 px-3 py-2 text-sm transition-colors focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/30 hover:border-ring/70 hover:bg-accent/70 dark:border-border/90 dark:bg-input/30 dark:hover:bg-accent/50"
                            >
                                <input
                                    type="checkbox"
                                    name="interests[]"
                                    :value="interest"
                                    class="size-4 rounded border-input accent-primary"
                                />
                                <span class="leading-5">{{ interest }}</span>
                            </Label>
                        </div>

                        <InputError :message="errors.interests" />

                        <div class="flex items-center">
                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="processing"
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
