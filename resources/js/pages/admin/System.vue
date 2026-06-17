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

type SystemStatus = {
    app_name: string;
    app_logo: string;
    admin_label: string;
    tagline: string;
    welcome_title: string;
    dashboard_title: string;
    show_admin_area: boolean;
    show_appearance_settings: boolean;
    environment: string;
    laravel_version: string;
    php_version: string;
    default_fields: string[];
};

const props = defineProps<{
    system: SystemStatus;
}>();

const page = usePage<{
    project: {
        adminLabel: string;
        dashboardTitle: string;
        dashboardDescription: string;
    };
}>();

const isDefaultField = (field: string) =>
    props.system.default_fields.includes(field);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'System',
                href: '/admin/system',
            },
        ],
    },
});
</script>

<template>
    <Head title="Systemstatus" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Systemstatus"
            :description="`System- und Laufzeitinformationen im Bereich ${page.props.project.adminLabel}.`"
        />

        <AdminNavigation />

        <PageSection>
            <div class="grid gap-6 xl:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Anwendung</CardTitle>
                        <CardDescription>
                            Grundlegende App- und Markenwerte.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    App-Name
                                </p>
                                <Badge
                                    v-if="isDefaultField('app_name')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    Standard
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.app_name }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Logo-Bezeichnung
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.app_logo }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Projektkonfiguration</CardTitle>
                        <CardDescription>
                            Gemeinsame Projektwerte für das Frontend.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Admin-Bezeichnung
                                </p>
                                <Badge
                                    v-if="isDefaultField('admin_label')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    Standard
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.admin_label }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Slogan
                                </p>
                                <Badge
                                    v-if="isDefaultField('tagline')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    Standard
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ props.system.tagline }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Willkommenstitel
                                </p>
                                <Badge
                                    v-if="isDefaultField('welcome_title')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    Standard
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.welcome_title }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Dashboard-Titel
                                </p>
                                <Badge
                                    v-if="isDefaultField('dashboard_title')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    Standard
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.dashboard_title }}
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
                                    props.system.show_admin_area
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
                                    props.system.show_appearance_settings
                                        ? 'Aktiviert'
                                        : 'Deaktiviert'
                                }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Laufzeit</CardTitle>
                        <CardDescription>
                            Informationen zu Umgebung und Framework.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Umgebung
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.environment }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Laravel-Version
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.laravel_version }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                PHP-Version
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.php_version }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
