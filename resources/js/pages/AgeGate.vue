<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Altersprüfung',
        description: 'NEAREON kann aktuell erst ab 14 Jahren genutzt werden.',
    },
});
</script>

<template>
    <Head title="Altersprüfung" />

    <Form
        action="/age-gate"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="birthdate">Geburtsdatum</Label>
                <Input
                    id="birthdate"
                    type="date"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="bday"
                    name="birthdate"
                />
                <p class="text-sm text-muted-foreground">
                    NEAREON kann aktuell erst ab 14 Jahren genutzt werden.
                </p>
                <InputError :message="errors.birthdate" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="2"
                :disabled="processing"
                data-test="age-gate-button"
            >
                <Spinner v-if="processing" />
                Weiter
            </Button>
        </div>
    </Form>
</template>
