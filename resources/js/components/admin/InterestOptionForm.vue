<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type InterestOptionFormData = {
    slug: string;
    label: string;
    sort_order: number;
    is_active?: boolean;
};

const props = withDefaults(
    defineProps<{
        action: string;
        idPrefix: string;
        method: 'post' | 'patch';
        submitLabel: string;
        interest?: InterestOptionFormData;
        showActiveField?: boolean;
    }>(),
    {
        interest: () => ({
            slug: '',
            label: '',
            sort_order: 0,
            is_active: true,
        }),
        showActiveField: false,
    },
);
</script>

<template>
    <Form
        :action="props.action"
        :method="props.method"
        class="space-y-5"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`${props.idPrefix}-slug`">Slug</Label>
                <Input
                    :id="`${props.idPrefix}-slug`"
                    name="slug"
                    type="text"
                    maxlength="80"
                    required
                    autocomplete="off"
                    placeholder="music"
                    :default-value="props.interest.slug"
                />
                <InputError :message="errors.slug" />
            </div>

            <div class="grid gap-2">
                <Label :for="`${props.idPrefix}-sort-order`">
                    Sortierung
                </Label>
                <Input
                    :id="`${props.idPrefix}-sort-order`"
                    name="sort_order"
                    type="number"
                    min="0"
                    step="1"
                    required
                    :default-value="props.interest.sort_order"
                />
                <InputError :message="errors.sort_order" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="`${props.idPrefix}-label`">Interesse</Label>
            <Input
                :id="`${props.idPrefix}-label`"
                name="label"
                type="text"
                maxlength="80"
                required
                autocomplete="off"
                placeholder="Musik"
                :default-value="props.interest.label"
            />
            <InputError :message="errors.label" />
        </div>

        <div
            v-if="props.showActiveField"
            class="flex items-center gap-3 rounded-md border border-border bg-background/60 px-3 py-3 dark:bg-input/20"
        >
            <input type="hidden" name="is_active" value="0" />
            <input
                :id="`${props.idPrefix}-is-active`"
                name="is_active"
                type="checkbox"
                value="1"
                :checked="props.interest.is_active ?? true"
                class="size-4 rounded border-input accent-primary"
            />
            <Label :for="`${props.idPrefix}-is-active`">
                Interesse direkt aktivieren
            </Label>
        </div>

        <InputError v-if="props.showActiveField" :message="errors.is_active" />

        <Button type="submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{ props.submitLabel }}
        </Button>
    </Form>
</template>
