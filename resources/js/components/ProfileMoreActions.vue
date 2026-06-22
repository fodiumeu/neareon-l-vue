<script setup lang="ts">
import { MoreHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';
import BlockActions from '@/components/BlockActions.vue';
import ReportDialog from '@/components/ReportDialog.vue';
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

defineProps<{
    isBlocked: boolean;
    username: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog :open="open" @update:open="open = $event">
        <DialogTrigger as-child>
            <Button variant="secondary" class="w-full">
                <MoreHorizontal aria-hidden="true" />
                Weitere Aktionen
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>Weitere Aktionen</DialogTitle>
                <DialogDescription>
                    Wähle eine weitere Aktion für dieses Profil.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <ReportDialog :username="username" />
                <BlockActions
                    :is-blocked="isBlocked"
                    :username="username"
                    @success="open = false"
                />
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button type="button" variant="secondary">
                        Schließen
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
