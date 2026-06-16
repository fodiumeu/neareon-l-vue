<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

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

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            :title="page.props.project.dashboardTitle"
            :description="page.props.project.dashboardDescription"
        />

        <PageSection v-if="page.props.project.hasStarterDefaults" padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">NEAREON-Basis aktiv</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Dieses Projekt nutzt jetzt die ersten
                        NEAREON-Setupwerte. Die naechsten Schritte bleiben
                        klein, bevor Fachfeatures gebaut werden.
                    </p>
                    <p
                        v-if="page.props.auth.user?.role === 'admin'"
                        class="text-sm text-muted-foreground"
                    >
                        Pruefe die aktuellen Projektwerte im
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

        <PageSection padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">Phase-0-Ueberblick</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Dieses Dashboard ist bewusst minimal. Es markiert den
                        NEAREON-Laravel-Aufbau, ohne Profil-, Discover- oder
                        Follow-Funktionen vorzutaeuschen.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Projektidentitaet</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            App-Metadaten und grundlegende Seitentexte zeigen
                            jetzt auf NEAREON.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Laravel-Zielsystem</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Dieses Repository bleibt das spaetere produktive
                            Laravel-System.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">MVP-Referenz</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Base44 bleibt die aktuelle Demo- und Testreferenz,
                            waehrend Laravel Schritt fuer Schritt vorbereitet
                            wird.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">
                        Naechster Setup-Schritt
                    </h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Fahre mit Sprache und Locale fort, bevor der erste
                        NEAREON-Feature-Slice startet.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
