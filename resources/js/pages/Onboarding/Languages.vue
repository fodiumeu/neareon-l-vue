<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    languages: string[];
}>();

const languageFields = ref([0]);

const addLanguage = () => {
    if (languageFields.value.length >= 5) {
        return;
    }

    languageFields.value = [
        ...languageFields.value,
        languageFields.value.length,
    ];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Deine Sprachen',
                href: '/onboarding/languages',
            },
        ],
    },
});
</script>

<template>
    <Head title="Deine Sprachen" />

    <div
        class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <div class="space-y-2">
            <p
                class="text-xs font-semibold tracking-wide text-primary uppercase"
            >
                Schritt 3 von 3
            </p>
            <PageHeader
                title="Deine Sprachen"
                description="Wähle deine Hauptsprache und optional weitere Sprachen aus."
            />
        </div>

        <PageSection padded>
            <Card
                class="bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
            >
                <CardContent>
                    <Form
                        action="/onboarding/languages"
                        method="post"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-4">
                            <div
                                v-for="(field, index) in languageFields"
                                :key="field"
                                class="grid gap-2"
                            >
                                <Label :for="`language-${field}`">
                                    {{
                                        index === 0
                                            ? 'Hauptsprache'
                                            : 'Weitere Sprache'
                                    }}
                                </Label>
                                <select
                                    :id="`language-${field}`"
                                    name="languages[]"
                                    :required="index === 0"
                                    class="flex h-10 w-full rounded-md border border-input bg-background/80 px-3 py-2 text-base shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:border-border/90 dark:bg-input/60"
                                >
                                    <option value="">Sprache auswählen</option>
                                    <option
                                        v-for="language in languages"
                                        :key="language"
                                        :value="language"
                                    >
                                        {{ language }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <InputError :message="errors.languages" />

                        <div class="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                class="w-full sm:w-auto"
                                :disabled="languageFields.length >= 5"
                                @click="addLanguage"
                            >
                                Weitere Sprache hinzufügen
                            </Button>

                            <Button
                                type="submit"
                                class="w-full sm:w-auto"
                                :disabled="processing"
                            >
                                <Spinner v-if="processing" />
                                Onboarding abschließen
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
