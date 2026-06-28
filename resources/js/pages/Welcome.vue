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

    <div
        class="dark relative flex min-h-screen w-full overflow-hidden bg-[#030318] text-foreground"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_14%_0%,rgba(90,69,200,0.34),transparent_32rem),radial-gradient(circle_at_86%_6%,rgba(167,255,82,0.16),transparent_24rem),linear-gradient(180deg,#030318_0%,#05051f_48%,#030318_100%)]"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -top-32 right-[-8rem] size-80 rounded-full bg-[var(--neareon-green)]/10 blur-3xl lg:size-96"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute bottom-12 left-[-10rem] size-80 rounded-full bg-action-primary/18 blur-3xl lg:size-96"
            aria-hidden="true"
        />

        <main
            class="relative z-10 mx-auto flex w-full max-w-(--app-content-max-width) flex-1 flex-col justify-between gap-12 px-5 py-6 sm:px-6 lg:px-8 lg:py-10"
        >
            <header class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-white/12 bg-white/8 shadow-2xl shadow-action-primary/20 backdrop-blur-xl"
                    >
                        <AppLogoIcon
                            class="size-6 fill-current text-white"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">
                            {{ page.props.app.branding.logo }}
                        </p>
                        <p class="truncate text-sm text-slate-300">
                            {{ page.props.project.tagline }}
                        </p>
                    </div>
                </div>

                <nav class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center rounded-full border border-white/15 bg-white/8 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
                    >
                        Home
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex items-center rounded-full border border-transparent px-3 py-2 text-sm font-medium text-slate-300 transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none sm:px-4"
                        >
                            Anmelden
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex items-center rounded-full bg-action-primary px-4 py-2 text-sm font-semibold text-action-primary-foreground shadow-lg shadow-action-primary/25 transition-colors hover:bg-action-primary-hover focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
                        >
                            Registrieren
                        </Link>
                    </template>
                </nav>
            </header>

            <section
                class="grid items-stretch gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)] lg:gap-8"
            >
                <div class="space-y-6">
                    <div class="space-y-3">
                        <p
                            class="inline-flex items-center rounded-full border border-[var(--neareon-green)]/25 bg-[var(--neareon-green)]/10 px-3 py-1 text-xs font-semibold tracking-[0.24em] text-[var(--neareon-green)] uppercase shadow-lg shadow-[var(--neareon-green)]/5"
                        >
                            Laravel Phase 0
                        </p>
                        <div class="space-y-4">
                            <h1
                                class="max-w-3xl text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-6xl"
                            >
                                {{ page.props.project.welcomeTitle }}
                            </h1>
                            <p
                                class="max-w-2xl text-base leading-7 text-slate-300 sm:text-lg"
                            >
                                {{ page.props.project.welcomeDescription }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div
                            class="rounded-3xl border border-white/12 bg-white/[0.07] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl"
                        >
                            <h2 class="text-sm font-semibold text-white">
                                Region
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                NEAREON wird als regionale Community-App
                                vorbereitet.
                            </p>
                        </div>
                        <div
                            class="rounded-3xl border border-white/12 bg-white/[0.07] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl"
                        >
                            <h2 class="text-sm font-semibold text-white">
                                Basis
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                Diese Laravel-Version bildet das spätere
                                Produktivsystem.
                            </p>
                        </div>
                        <div
                            class="rounded-3xl border border-white/12 bg-white/[0.07] p-5 shadow-2xl shadow-black/20 backdrop-blur-xl"
                        >
                            <h2 class="text-sm font-semibold text-white">
                                Referenz
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                Der Base44-MVP bleibt fachliche Demo- und
                                Testreferenz.
                            </p>
                        </div>
                    </div>
                </div>

                <aside
                    class="relative flex min-w-0 flex-col justify-between overflow-hidden rounded-3xl border border-white/12 bg-white/[0.08] p-6 shadow-2xl shadow-black/30 backdrop-blur-2xl"
                >
                    <div
                        class="pointer-events-none absolute -top-16 -right-16 size-36 rounded-full bg-action-primary/22 blur-3xl"
                        aria-hidden="true"
                    />
                    <div class="space-y-3">
                        <h2 class="text-lg font-semibold text-white">
                            NEAREON Teststand
                        </h2>
                        <p class="text-sm leading-6 text-slate-300">
                            Dieser Aufbau bereitet die Laravel-Basis vor, bevor
                            erste NEAREON-Fachmodule entstehen.
                        </p>
                    </div>

                    <div class="mt-8 space-y-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex w-full items-center justify-center rounded-full bg-action-primary px-4 py-2.5 text-sm font-semibold text-action-primary-foreground shadow-lg shadow-action-primary/25 transition-colors hover:bg-action-primary-hover focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
                        >
                            Home öffnen
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex w-full items-center justify-center rounded-full bg-action-primary px-4 py-2.5 text-sm font-semibold text-action-primary-foreground shadow-lg shadow-action-primary/25 transition-colors hover:bg-action-primary-hover focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
                            >
                                Anmelden
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex w-full items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-white/12 focus-visible:ring-2 focus-visible:ring-[var(--neareon-green)] focus-visible:ring-offset-2 focus-visible:ring-offset-[#030318] focus-visible:outline-none"
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
