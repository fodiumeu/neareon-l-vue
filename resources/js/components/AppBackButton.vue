<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { navigateBack } from '@/lib/appNavigationHistory';

const props = withDefaults(
    defineProps<{
        fallback: string;
        label?: string;
    }>(),
    {
        label: 'Zurück',
    },
);

const goBack = () => navigateBack(props.fallback);
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="sm"
        class="w-fit px-2 text-muted-foreground hover:text-foreground"
        data-test="app-back-button"
        @click="goBack"
    >
        <ArrowLeft aria-hidden="true" />
        {{ label }}
    </Button>
</template>
