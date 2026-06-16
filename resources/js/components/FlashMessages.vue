<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleAlert } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const page = usePage<{
    flash: {
        success: string | null;
        error: string | null;
    };
}>();
</script>

<template>
    <div
        v-if="page.props.flash.success || page.props.flash.error"
        class="space-y-3 px-4 pt-4"
    >
        <Alert v-if="page.props.flash.success">
            <CheckCircle2 />
            <AlertTitle>Erfolg</AlertTitle>
            <AlertDescription>
                <p>{{ page.props.flash.success }}</p>
            </AlertDescription>
        </Alert>

        <Alert v-if="page.props.flash.error" variant="destructive">
            <CircleAlert />
            <AlertTitle>Fehler</AlertTitle>
            <AlertDescription>
                <p>{{ page.props.flash.error }}</p>
            </AlertDescription>
        </Alert>
    </div>
</template>
