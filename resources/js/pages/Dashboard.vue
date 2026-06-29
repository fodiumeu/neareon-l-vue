<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/neareon-profile';

const page = usePage<{
    project: {
        dashboardTitle: string;
        dashboardDescription: string;
        hasStarterDefaults: boolean;
    };
    auth: {
        user: {
            role: string;
        } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Home',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="page.props.project.dashboardTitle" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-auto p-4 sm:p-6"
    >
        <PageHeader
            title="Willkommen bei NEAREON"
            description="Dein persönlicher Einstieg in Profil, Community und Discover."
        />

        <PageSection>
            <Card
                class="overflow-hidden bg-card/95 shadow-lg shadow-black/10 dark:border-primary/20 dark:shadow-black/35"
            >
                <CardContent class="relative space-y-6">
                    <div
                        class="pointer-events-none absolute -top-24 -right-20 size-56 rounded-full bg-action-primary/14 blur-3xl"
                        aria-hidden="true"
                    />
                    <div class="max-w-3xl space-y-3">
                        <p
                            class="relative text-xs font-semibold tracking-[0.18em] text-primary uppercase"
                        >
                            Home
                        </p>
                        <h2
                            class="relative text-2xl font-semibold tracking-tight"
                        >
                            Baue dein NEAREON-Profil weiter aus.
                        </h2>
                        <p
                            class="relative text-sm leading-6 text-muted-foreground"
                        >
                            Halte dein Profil aktuell, entdecke sichtbare
                            Community-Profile und nutze die vorhandenen
                            NEAREON-Funktionen ohne Umwege.
                        </p>
                    </div>

                    <div class="relative flex flex-col gap-3 sm:flex-row">
                        <Button as-child>
                            <Link href="/explore">Entdecken</Link>
                        </Button>
                        <Button as-child variant="secondary">
                            <Link :href="editProfile()">Profil bearbeiten</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection v-if="page.props.project.hasStarterDefaults">
            <Card class="border-primary/30 bg-primary/10 shadow-primary/5">
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">NEAREON-Basis aktiv</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Die zentralen Projektwerte zeigen auf NEAREON. Weitere
                        Schritte bleiben bewusst klein und nachvollziehbar.
                    </p>
                    <p
                        v-if="page.props.auth.user?.role === 'admin'"
                        class="text-sm text-muted-foreground"
                    >
                        Prüfe die aktuellen Projektwerte im
                        <Link
                            href="/admin/system"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                        >
                            Systemstatus
                        </Link>
                        .
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardContent class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-full border border-border bg-accent text-sm font-semibold text-accent-foreground"
                        >
                            01
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-sm font-medium">
                                Profil vervollständigen
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Pflege Anzeigename, Bio, Region, Interessen und
                                Sprachen an einer Stelle.
                            </p>
                        </div>
                        <Button as-child variant="secondary" class="w-full">
                            <Link :href="editProfile()">Profil bearbeiten</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-full border border-border bg-accent text-sm font-semibold text-accent-foreground"
                        >
                            02
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-sm font-medium">Entdecken</h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Finde Mitglieder, Gruppen und Events aus der
                                Community.
                            </p>
                        </div>
                        <Button as-child variant="secondary" class="w-full">
                            <Link href="/explore">Entdecken öffnen</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-full border border-border bg-accent text-sm font-semibold text-accent-foreground"
                        >
                            03
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-sm font-medium">
                                Profil bearbeiten
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Aktualisiere sichtbare Angaben, ohne den
                                Account-Namen oder Systemdaten zu verändern.
                            </p>
                        </div>
                        <Button as-child variant="secondary" class="w-full">
                            <Link :href="editProfile()">Bearbeiten</Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-full border border-border bg-accent text-sm font-semibold text-accent-foreground"
                        >
                            04
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-sm font-medium">
                                Nächste Schritte
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Discover, Profile und Follow-Funktionen bleiben
                                die aktuelle Basis für die nächsten Module.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
