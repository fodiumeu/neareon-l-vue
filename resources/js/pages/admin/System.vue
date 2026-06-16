<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
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

const isDefaultField = (field: string) => props.system.default_fields.includes(field);

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
    <Head title="System status" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="System status"
            :description="`Read-only system and starter-kit status inside ${page.props.project.adminLabel.toLowerCase()}.`"
        />

        <PageSection>
            <div class="grid gap-6 xl:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Application</CardTitle>
                        <CardDescription>
                            Basic app and branding values.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    App name
                                </p>
                                <Badge
                                    v-if="isDefaultField('app_name')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    default
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.app_name }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Logo label
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.app_logo }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Project configuration</CardTitle>
                        <CardDescription>
                            Shared project values exposed to the frontend.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Admin label
                                </p>
                                <Badge
                                    v-if="isDefaultField('admin_label')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    default
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.admin_label }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Tagline
                                </p>
                                <Badge
                                    v-if="isDefaultField('tagline')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    default
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ props.system.tagline }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Welcome title
                                </p>
                                <Badge
                                    v-if="isDefaultField('welcome_title')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    default
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.welcome_title }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    Dashboard title
                                </p>
                                <Badge
                                    v-if="isDefaultField('dashboard_title')"
                                    variant="secondary"
                                    class="capitalize"
                                >
                                    default
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.dashboard_title }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Show admin area
                            </p>
                            <Badge variant="secondary" class="mt-2 capitalize">
                                {{
                                    props.system.show_admin_area
                                        ? 'enabled'
                                        : 'disabled'
                                }}
                            </Badge>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Show appearance settings
                            </p>
                            <Badge variant="secondary" class="mt-2 capitalize">
                                {{
                                    props.system.show_appearance_settings
                                        ? 'enabled'
                                        : 'disabled'
                                }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Runtime</CardTitle>
                        <CardDescription>
                            Environment and framework runtime information.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Environment
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.environment }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Laravel version
                            </p>
                            <p class="mt-1 text-sm font-medium">
                                {{ props.system.laravel_version }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-border px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                PHP version
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
