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

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Deine Interessen"
            description="Wähle Themen aus, die zu dir passen."
        />

        <PageSection padded>
            <Card>
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
                                class="flex items-center gap-3 rounded-md border border-input px-3 py-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="interests[]"
                                    :value="interest"
                                    class="size-4 rounded border-input"
                                />
                                <span>{{ interest }}</span>
                            </Label>
                        </div>

                        <InputError :message="errors.interests" />

                        <div class="flex items-center">
                            <Button type="submit" :disabled="processing">
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
