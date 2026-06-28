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
        class="dark relative flex min-h-svh w-full overflow-hidden bg-[#030318] px-4 py-8 text-foreground sm:px-6 lg:px-8"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_0%,rgba(90,69,200,0.32),transparent_30rem),radial-gradient(circle_at_86%_8%,rgba(167,255,82,0.16),transparent_24rem),linear-gradient(180deg,#030318_0%,#05051f_50%,#030318_100%)]"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -top-28 left-1/2 size-72 -translate-x-1/2 rounded-full bg-action-primary/18 blur-3xl sm:size-96"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute right-[-6rem] bottom-10 size-64 rounded-full bg-[var(--neareon-green)]/10 blur-3xl"
            aria-hidden="true"
        />

        <div
            class="relative z-10 flex min-h-[calc(100svh-4rem)] w-full items-center justify-center"
        >
            <div class="w-full max-w-md">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col items-center gap-4 text-center">
                        <Link
                            :href="home()"
                            class="group flex flex-col items-center gap-3 font-medium focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
                        >
                            <div
                                class="flex size-14 items-center justify-center rounded-2xl border border-white/12 bg-white/8 shadow-2xl shadow-action-primary/20 backdrop-blur-xl transition-transform duration-200 group-hover:-translate-y-0.5"
                            >
                                <AppLogoIcon
                                    class="size-9 fill-current text-white"
                                />
                            </div>
                            <span class="sr-only">{{ title }}</span>
                        </Link>
                        <div class="space-y-2">
                            <p
                                class="text-xs font-semibold tracking-[0.28em] text-[var(--neareon-green)] uppercase"
                            >
                                NEAREON
                            </p>
                            <h1
                                class="text-2xl font-semibold tracking-tight text-white"
                            >
                                {{ title }}
                            </h1>
                            <p class="text-sm leading-6 text-slate-300">
                                {{ description }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-3xl border border-white/12 bg-white/[0.07] p-5 shadow-2xl shadow-black/30 backdrop-blur-2xl sm:p-6"
                    >
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
