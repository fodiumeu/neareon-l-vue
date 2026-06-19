<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    username: string;
}>();

const open = ref(false);

const reasons = [
    { value: 'spam', label: 'Spam' },
    { value: 'harassment', label: 'Belästigung' },
    { value: 'fake_profile', label: 'Fake-Profil' },
    { value: 'inappropriate_content', label: 'Unangemessene Inhalte' },
    { value: 'fraud', label: 'Betrug' },
    { value: 'other', label: 'Sonstiges' },
];
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogTrigger as-child>
            <Button variant="secondary" class="w-full">Melden</Button>
        </DialogTrigger>

        <DialogContent>
            <Form
                :action="`/u/${username}/reports`"
                method="post"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Benutzer melden</DialogTitle>
                    <DialogDescription>
                        Wähle einen Grund aus. Die Meldung wird anschließend von
                        einem Administrator geprüft.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="report-reason">Grund</Label>
                        <select
                            id="report-reason"
                            name="reason"
                            required
                            class="h-9 w-full rounded-md border border-input bg-background/80 px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/45 dark:border-border/90 dark:bg-input/60"
                        >
                            <option value="" disabled selected>
                                Grund auswählen
                            </option>
                            <option
                                v-for="reason in reasons"
                                :key="reason.value"
                                :value="reason.value"
                            >
                                {{ reason.label }}
                            </option>
                        </select>
                        <InputError :message="errors.reason" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="report-description">
                            Beschreibung (optional)
                        </Label>
                        <textarea
                            id="report-description"
                            name="description"
                            maxlength="1000"
                            rows="5"
                            class="min-h-28 w-full resize-y rounded-md border border-input bg-background/80 px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/45 dark:border-border/90 dark:bg-input/60"
                            placeholder="Zusätzliche Informationen zur Meldung"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="processing"
                        >
                            Abbrechen
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Meldung senden
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
