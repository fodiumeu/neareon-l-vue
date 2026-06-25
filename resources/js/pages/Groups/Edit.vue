<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBackButton from '@/components/AppBackButton.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type VisibilityOption = {
    value: 'public' | 'request' | 'private';
    label: string;
    description: string;
};

type CategoryOption = {
    id: number;
    slug: string;
    label: string;
};

type EditableGroup = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country_code?: string | null;
    visibility: 'public' | 'request' | 'private';
    category_interest_option_id?: number | null;
    category?: CategoryOption | null;
    url: string;
};

const props = defineProps<{
    categoryOptions: CategoryOption[];
    group: EditableGroup;
    visibilityOptions: VisibilityOption[];
}>();

const noCategoryValue = '__none__';
const selectedCategoryOption = ref(
    props.group.category_interest_option_id
        ? String(props.group.category_interest_option_id)
        : noCategoryValue,
);
const categoryInputValue = computed(() =>
    selectedCategoryOption.value === noCategoryValue
        ? ''
        : selectedCategoryOption.value,
);
const updateAction = computed(() => `/groups/${props.group.slug}`);
const groupSelectTriggerClass =
    'w-full border-input bg-background text-foreground hover:border-ring/70 hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:border-border/90 dark:bg-input/35 dark:hover:bg-accent/45';
const groupSelectContentClass =
    'border-border bg-popover text-popover-foreground shadow-xl shadow-black/30';
const groupSelectItemClass =
    'focus:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] focus:text-popover-foreground data-[highlighted]:bg-[color-mix(in_oklab,var(--neareon-green)_55%,var(--popover))] data-[highlighted]:text-popover-foreground data-[state=checked]:bg-action-primary data-[state=checked]:text-action-primary-foreground data-[state=checked]:focus:bg-action-primary data-[state=checked]:focus:text-action-primary-foreground';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Gruppe bearbeiten',
                href: '/groups',
            },
        ],
    },
});
</script>

<template>
    <Head title="Gruppe bearbeiten" />

    <div
        class="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <AppBackButton
            :fallback="group.url"
            label="Zurück zur Gruppe"
            class="hidden md:inline-flex"
        />

        <PageHeader
            title="Gruppe bearbeiten"
            description="Aktualisiere die Angaben deiner Gruppe."
        />

        <PageSection>
            <Card>
                <CardContent class="p-5">
                    <Form
                        :action="updateAction"
                        method="post"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <input type="hidden" name="_method" value="patch" />

                        <div class="grid gap-2">
                            <Label for="name">Gruppenname</Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                maxlength="80"
                                required
                                autocomplete="off"
                                :default-value="group.name"
                            />
                            <p class="text-sm text-muted-foreground">
                                Der Slug der Gruppe bleibt beim Bearbeiten
                                unverändert.
                            </p>
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Beschreibung</Label>
                            <textarea
                                id="description"
                                name="description"
                                maxlength="1000"
                                rows="5"
                                placeholder="Beschreibe kurz, worum es in deiner Gruppe geht."
                                :value="group.description ?? ''"
                                class="flex min-h-28 w-full resize-y rounded-md border border-input bg-background/80 px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:border-border/90 dark:bg-input/40"
                            />
                            <p class="text-sm text-muted-foreground">
                                Beschreibe kurz, worum es in deiner Gruppe geht.
                            </p>
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2 md:col-span-1">
                                <Label for="region">Region</Label>
                                <Input
                                    id="region"
                                    name="region"
                                    type="text"
                                    maxlength="120"
                                    :default-value="group.region ?? ''"
                                />
                                <InputError :message="errors.region" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="postal_code">Postleitzahl</Label>
                                <Input
                                    id="postal_code"
                                    name="postal_code"
                                    type="text"
                                    maxlength="20"
                                    inputmode="text"
                                    :default-value="group.postal_code ?? ''"
                                />
                                <InputError :message="errors.postal_code" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="country_code">Land</Label>
                                <Input
                                    id="country_code"
                                    name="country_code"
                                    type="text"
                                    maxlength="2"
                                    :default-value="group.country_code ?? ''"
                                />
                                <InputError :message="errors.country_code" />
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Für Gruppen wird nur eine grobe Region bzw.
                            Postleitzahl verwendet – keine genaue Adresse.
                        </p>

                        <div class="grid gap-2">
                            <Label for="category_interest_option_id">
                                Kategorie
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                Wähle ein Hauptthema, damit andere deine
                                Gruppe später besser finden können.
                            </p>
                            <input
                                type="hidden"
                                name="category_interest_option_id"
                                :value="categoryInputValue"
                            />
                            <Select v-model="selectedCategoryOption">
                                <SelectTrigger
                                    id="category_interest_option_id"
                                    :class="groupSelectTriggerClass"
                                    aria-label="Kategorie auswählen"
                                >
                                    <SelectValue placeholder="Keine Kategorie" />
                                </SelectTrigger>
                                <SelectContent :class="groupSelectContentClass">
                                    <SelectItem
                                        :value="noCategoryValue"
                                        :class="groupSelectItemClass"
                                    >
                                        Keine Kategorie
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in categoryOptions"
                                        :key="option.id"
                                        :value="String(option.id)"
                                        :class="groupSelectItemClass"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="categoryOptions.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                Aktuell ist keine Kategorie verfügbar.
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Optional. Wenn mehrere Themen passen, wähle das
                                wichtigste Hauptthema.
                            </p>
                            <InputError
                                :message="errors.category_interest_option_id"
                            />
                        </div>

                        <div class="space-y-3">
                            <div class="space-y-1">
                                <Label for="visibility-public">
                                    Sichtbarkeit
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Lege fest, wie deine Gruppe gefunden werden
                                    kann.
                                </p>
                            </div>

                            <div class="grid gap-3">
                                <Label
                                    v-for="option in visibilityOptions"
                                    :key="option.value"
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-input bg-background/70 px-3 py-3 text-sm transition-colors hover:border-ring/70 hover:bg-accent/70"
                                >
                                    <input
                                        :id="`visibility-${option.value}`"
                                        type="radio"
                                        name="visibility"
                                        :value="option.value"
                                        :checked="option.value === group.visibility"
                                        class="mt-1 size-4 border-input accent-primary"
                                    />
                                    <span class="grid gap-1">
                                        <span class="font-medium">
                                            {{ option.label }}
                                        </span>
                                        <span
                                            class="leading-5 text-muted-foreground"
                                        >
                                            {{ option.description }}
                                        </span>
                                    </span>
                                </Label>
                            </div>
                            <InputError :message="errors.visibility" />
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Button type="submit" :disabled="processing">
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
