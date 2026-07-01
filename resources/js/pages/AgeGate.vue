<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const birthdateDisplay = ref('');
const birthdateValue = ref('');
const calendarInput = ref<HTMLInputElement | null>(null);

const datePartsToIso = (day: number, month: number, year: number) => {
    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }

    return [
        String(year).padStart(4, '0'),
        String(month).padStart(2, '0'),
        String(day).padStart(2, '0'),
    ].join('-');
};

const normalizeBirthdate = (value: string) => {
    const trimmed = value.trim();

    const dotted = trimmed.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);

    if (dotted) {
        return datePartsToIso(
            Number(dotted[1]),
            Number(dotted[2]),
            Number(dotted[3]),
        );
    }

    const compact = trimmed.match(/^(\d{2})(\d{2})(\d{4})$/);

    if (compact) {
        return datePartsToIso(
            Number(compact[1]),
            Number(compact[2]),
            Number(compact[3]),
        );
    }

    const iso = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (iso) {
        return datePartsToIso(Number(iso[3]), Number(iso[2]), Number(iso[1]));
    }

    return null;
};

const formatBirthdateDisplay = (value: string) => {
    const iso = normalizeBirthdate(value);

    if (!iso) {
        return value;
    }

    const [year, month, day] = iso.split('-');

    return `${day}.${month}.${year}`;
};

const updateBirthdateFromText = (value: string | number) => {
    const nextValue = String(value);

    birthdateDisplay.value = nextValue;
    birthdateValue.value = normalizeBirthdate(nextValue) ?? nextValue;
};

const finalizeBirthdateText = () => {
    birthdateDisplay.value = formatBirthdateDisplay(birthdateDisplay.value);
};

const updateBirthdateFromCalendar = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;

    birthdateValue.value = value;
    birthdateDisplay.value = formatBirthdateDisplay(value);
};

const openCalendar = () => {
    const input = calendarInput.value;

    if (!input) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        input.showPicker();

        return;
    }

    input.focus();
    input.click();
};

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
            <div
                class="rounded-lg border border-border/70 bg-background/60 p-4 dark:bg-input/20"
            >
                <div class="grid gap-2">
                    <Label for="birthdate">Geburtsdatum</Label>
                    <div class="relative flex items-center">
                        <Input
                            id="birthdate"
                            :model-value="birthdateDisplay"
                            type="text"
                            required
                            autofocus
                            class="pr-11"
                            :tabindex="1"
                            autocomplete="bday"
                            inputmode="numeric"
                            placeholder="TT.MM.JJJJ"
                            aria-describedby="birthdate-help"
                            :aria-invalid="Boolean(errors.birthdate)"
                            @update:model-value="updateBirthdateFromText"
                            @blur="finalizeBirthdateText"
                        />
                        <input
                            v-model="birthdateValue"
                            type="hidden"
                            name="birthdate"
                        />
                        <input
                            ref="calendarInput"
                            type="date"
                            class="pointer-events-none absolute right-3 size-0 opacity-0"
                            tabindex="-1"
                            aria-hidden="true"
                            @change="updateBirthdateFromCalendar"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="absolute right-1 size-8 text-muted-foreground hover:text-foreground"
                            tabindex="2"
                            aria-label="Kalender öffnen"
                            @click="openCalendar"
                        >
                            <CalendarDays class="size-4" />
                        </Button>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Gib dein Geburtsdatum als TT.MM.JJJJ ein. NEAREON kann
                        aktuell erst ab 14 Jahren genutzt werden.
                    </p>
                    <InputError :message="errors.birthdate" />
                </div>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="3"
                :disabled="processing"
                data-test="age-gate-button"
            >
                <Spinner v-if="processing" />
                Weiter
            </Button>
        </div>
    </Form>
</template>
