<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background px-4 py-8 text-foreground sm:px-6 lg:px-8"
    >
        <div class="w-full max-w-md">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col items-center gap-4 text-center">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-3 font-medium"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-xl border border-border/80 bg-card/90 shadow-sm shadow-primary/10 backdrop-blur-sm"
                        >
                            <AppLogoIcon
                                class="size-9 fill-current text-foreground dark:text-white"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2">
                        <p
                            class="text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            NEAREON
                        </p>
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div
                    class="rounded-xl border border-border/80 bg-card/90 p-5 shadow-lg shadow-black/10 backdrop-blur-sm sm:p-6 dark:shadow-black/30"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
