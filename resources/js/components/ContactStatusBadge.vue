<script setup lang="ts">
import { computed } from 'vue';
import type { ContactStatus } from '@/types';

const props = defineProps<{
    status: ContactStatus;
}>();

const label = computed(
    () =>
        ({
            none: 'Keine Verbindung',
            outgoing_request: 'Kontaktanfrage gesendet',
            incoming_request: 'Kontaktanfrage erhalten',
            connected: 'Verbunden',
        })[props.status],
);
</script>

<template>
    <span
        :class="
            status === 'none'
                ? 'border-border bg-background/70 text-muted-foreground dark:bg-input/30'
                : 'border-primary/30 bg-primary/10 text-primary'
        "
        class="rounded-full border px-3 py-1 text-xs font-medium"
    >
        {{ label }}
    </span>
</template>
