<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    isBlocked: boolean;
    username: string;
}>();

const emit = defineEmits<{
    success: [];
}>();

const confirmationOpen = ref(false);

const handleSuccess = () => {
    confirmationOpen.value = false;
    emit('success');
};
</script>

<template>
    <Form
        v-if="isBlocked"
        :action="`/u/${username}/block`"
        method="delete"
        :options="{ preserveScroll: true }"
        v-slot="{ processing }"
        @success="handleSuccess"
    >
        <Button
            type="submit"
            variant="secondary"
            :disabled="processing"
            class="w-full"
        >
            <Spinner v-if="processing" />
            Blockierung aufheben
        </Button>
    </Form>

    <Dialog
        v-else
        :open="confirmationOpen"
        @update:open="confirmationOpen = $event"
    >
        <DialogTrigger as-child>
            <Button variant="destructive" class="w-full"> Blockieren </Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                :action="`/u/${username}/block`"
                method="post"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
                class="space-y-6"
                @success="handleSuccess"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Benutzer blockieren?</DialogTitle>
                    <DialogDescription>
                        Dieser Benutzer kann dir keine Kontaktanfragen mehr
                        senden und keine neuen Nachrichten mehr mit dir
                        austauschen.
                    </DialogDescription>
                </DialogHeader>

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
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        Blockieren
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
