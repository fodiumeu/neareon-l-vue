<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ContactRequestStatus } from '@/types';

const props = defineProps<{
    status: ContactRequestStatus;
}>();

const label = computed(
    () =>
        ({
            pending: 'Ausstehend',
            accepted: 'Angenommen',
            declined: 'Abgelehnt',
            closed: 'Geschlossen',
        })[props.status],
);
</script>

<template>
    <Badge
        :variant="status === 'accepted' ? 'default' : 'secondary'"
        :class="{
            'border border-destructive/30 bg-destructive/10 text-destructive dark:bg-destructive/15':
                status === 'declined',
        }"
    >
        {{ label }}
    </Badge>
</template>
