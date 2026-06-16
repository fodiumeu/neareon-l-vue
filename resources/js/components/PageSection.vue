<script setup lang="ts">
type Props = {
    padded?: boolean;
};

withDefaults(defineProps<Props>(), {
    padded: false,
});
</script>

<template>
    <section
        class="space-y-6"
        :class="{
            'rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border':
                padded,
        }"
    >
        <slot />
    </section>
</template>
