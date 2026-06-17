<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AdminNavigation from '@/components/AdminNavigation.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type ProjectOverview = {
    appName: string;
    logo: string;
    adminLabel: string;
    tagline: string;
    showAdminArea: boolean;
    showAppearanceSettings: boolean;
};

const props = defineProps<{
    overview: ProjectOverview;
}>();

const page = usePage<{
    project: {
        adminLabel: string;
        dashboardTitle: string;
        dashboardDescription: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Projekt',
                href: '/admin/project',
            },
        ],
    },
});
</script>

<template>
    <Head title="Projektübersicht" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Projektübersicht"
            :description="`Zentrale Projektkonfiguration im Bereich ${page.props.project.adminLabel}.`"
        />

        <AdminNavigation />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>Projekteinstellungen</CardTitle>
                    <CardDescription>
                        Zentrale App- und Projektwerte, die aktuell im Frontend
                        bereitgestellt werden.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            App-Name
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.appName }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Logo-Bezeichnung
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.logo }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Admin-Bezeichnung
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.adminLabel }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Slogan
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.overview.tagline }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Dashboard-Titel
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ page.props.project.dashboardTitle }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Dashboard-Beschreibung
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ page.props.project.dashboardDescription }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Adminbereich anzeigen
                        </p>
                        <Badge variant="secondary" class="mt-2 capitalize">
                            {{
                                props.overview.showAdminArea
                                    ? 'Aktiviert'
                                    : 'Deaktiviert'
                            }}
                        </Badge>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Darstellungseinstellungen anzeigen
                        </p>
                        <Badge variant="secondary" class="mt-2 capitalize">
                            {{
                                props.overview.showAppearanceSettings
                                    ? 'Aktiviert'
                                    : 'Deaktiviert'
                            }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
