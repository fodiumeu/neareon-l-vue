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
            'rounded-xl border border-border/70 p-4 dark:border-border/80':
                padded,
        }"
    >
        <slot />
    </section>
</template>
