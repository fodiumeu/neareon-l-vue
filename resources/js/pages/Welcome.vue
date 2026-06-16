<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page = usePage<{
    app: {
        name: string;
        branding: {
            logo: string;
        };
    };
    project: {
        tagline: string;
        welcomeTitle: string;
        welcomeDescription: string;
    };
}>();
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen bg-background text-foreground">
        <main
            class="mx-auto flex w-full max-w-(--app-content-max-width) flex-1 flex-col justify-between gap-12 px-6 py-8 lg:px-8 lg:py-10"
        >
            <header class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
                    >
                        <AppLogoIcon
                            class="size-5 fill-current text-white dark:text-black"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">
                            {{ page.props.app.branding.logo }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ page.props.project.tagline }}
                        </p>
                    </div>
                </div>

                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <section class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <div class="space-y-6">
                    <div class="space-y-3">
                        <p
                            class="inline-flex items-center rounded-full border border-border px-3 py-1 text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground"
                        >
                            Starter Kit
                        </p>
                        <div class="space-y-4">
                            <h1
                                class="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl"
                            >
                                {{ page.props.project.welcomeTitle }}
                            </h1>
                            <p
                                class="max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg"
                            >
                                {{ page.props.project.welcomeDescription }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-border bg-card p-5">
                            <h2 class="text-sm font-medium">Navigation</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Central app and settings navigation with room
                                for role-aware expansion.
                            </p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-5">
                            <h2 class="text-sm font-medium">Branding</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Shared app metadata keeps naming and visual
                                basics easy to adjust.
                            </p>
                        </div>
                        <div class="rounded-xl border border-border bg-card p-5">
                            <h2 class="text-sm font-medium">Admin Basis</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                A small protected admin area is ready for
                                further expansion.
                            </p>
                        </div>
                    </div>
                </div>

                <aside
                    class="flex flex-col justify-between rounded-2xl border border-border bg-card p-6"
                >
                    <div class="space-y-3">
                        <h2 class="text-lg font-semibold">Get started</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Use this project as a clean base for future web
                            applications and extend it step by step.
                        </p>
                    </div>

                    <div class="mt-8 space-y-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Open dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex w-full items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                            >
                                Create account
                            </Link>
                        </template>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</template>
