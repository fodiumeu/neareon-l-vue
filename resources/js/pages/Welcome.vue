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
    <Head title="Willkommen" />

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
                        Home
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Anmelden
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Registrieren
                        </Link>
                    </template>
                </nav>
            </header>

            <section
                class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]"
            >
                <div class="space-y-6">
                    <div class="space-y-3">
                        <p
                            class="inline-flex items-center rounded-full border border-border px-3 py-1 text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Laravel Phase 0
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
                        <div
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <h2 class="text-sm font-medium">Region</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                NEAREON wird als regionale Community-App
                                vorbereitet.
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <h2 class="text-sm font-medium">Basis</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Diese Laravel-Version bildet das spätere
                                Produktivsystem.
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border bg-card p-5"
                        >
                            <h2 class="text-sm font-medium">Referenz</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Der Base44-MVP bleibt fachliche Demo- und
                                Testreferenz.
                            </p>
                        </div>
                    </div>
                </div>

                <aside
                    class="flex flex-col justify-between rounded-2xl border border-border bg-card p-6"
                >
                    <div class="space-y-3">
                        <h2 class="text-lg font-semibold">NEAREON Teststand</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Dieser Aufbau bereitet die Laravel-Basis vor, bevor
                            erste NEAREON-Fachmodule entstehen.
                        </p>
                    </div>

                    <div class="mt-8 space-y-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                        >
                            Home öffnen
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            >
                                Anmelden
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex w-full items-center justify-center rounded-md border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
                            >
                                Account erstellen
                            </Link>
                        </template>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</template>
