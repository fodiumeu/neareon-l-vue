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
                title: 'Dashboard',
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
                    <h2 class="text-base font-medium">Starter defaults active</h2>
                    <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                        This project still uses starter defaults. Review
                        branding and project settings before building features.
                    </p>
                    <p
                        v-if="page.props.auth.user?.role === 'admin'"
                        class="text-sm text-muted-foreground"
                    >
                        Review the current starter values in
                        <Link
                            href="/admin/system"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                        >
                            System status
                        </Link>
                        .
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">Workspace overview</h2>
                    <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                        This dashboard is intentionally minimal. It gives new
                        projects a neutral signed-in starting point without
                        assuming domain-specific metrics, workflows or modules.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Navigation ready</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Core app and settings navigation are centralized and
                            can be extended with minimal file changes.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Branding ready</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Shared app metadata and small design tokens are
                            already in place for future reuse across projects.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Admin basis ready</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Role-aware navigation and a protected admin area
                            provide a small but real platform foundation.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">
                        Next project-specific step
                    </h2>
                    <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                        Replace this page with the first real workspace view for
                        your application, such as a task overview, operations
                        dashboard, customer area or internal control panel.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
