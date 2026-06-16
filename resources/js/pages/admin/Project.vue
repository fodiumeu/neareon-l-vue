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
                title: 'Project',
                href: '/admin/project',
            },
        ],
    },
});
</script>

<template>
    <Head title="Project overview" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Project overview"
            :description="`Read-only project configuration inside ${page.props.project.adminLabel.toLowerCase()}.`"
        />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>Project settings</CardTitle>
                    <CardDescription>
                        Core app and project values currently shared with the
                        frontend.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            App name
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.appName }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Logo label
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.logo }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Admin label
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ props.overview.adminLabel }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Tagline
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.overview.tagline }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Dashboard title
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ page.props.project.dashboardTitle }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Dashboard description
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ page.props.project.dashboardDescription }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Show admin area
                        </p>
                        <Badge variant="secondary" class="mt-2 capitalize">
                            {{
                                props.overview.showAdminArea
                                    ? 'enabled'
                                    : 'disabled'
                            }}
                        </Badge>
                    </div>

                    <div class="rounded-lg border border-border px-4 py-3">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Show appearance settings
                        </p>
                        <Badge variant="secondary" class="mt-2 capitalize">
                            {{
                                props.overview.showAppearanceSettings
                                    ? 'enabled'
                                    : 'disabled'
                            }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
