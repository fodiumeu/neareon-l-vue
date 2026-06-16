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
                    <h2 class="text-base font-medium">NEAREON basis active</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        This project now uses the first NEAREON setup values.
                        Keep the next steps small before building features.
                    </p>
                    <p
                        v-if="page.props.auth.user?.role === 'admin'"
                        class="text-sm text-muted-foreground"
                    >
                        Review the current project values in
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
                    <h2 class="text-base font-medium">Phase 0 overview</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        This dashboard is intentionally minimal. It marks the
                        NEAREON Laravel setup without pretending that profile,
                        discover or follow features already exist.
                    </p>
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Project identity</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            App metadata and basic page copy now point to
                            NEAREON.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">Laravel target</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            This repository remains the future productive
                            Laravel system.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="space-y-2">
                        <h2 class="text-sm font-medium">MVP reference</h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Base44 remains the current demo and test reference
                            while Laravel is prepared step by step.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </PageSection>

        <PageSection padded>
            <Card>
                <CardContent class="space-y-3">
                    <h2 class="text-base font-medium">Next setup step</h2>
                    <p
                        class="max-w-3xl text-sm leading-6 text-muted-foreground"
                    >
                        Continue with language and locale preparation before
                        starting the first NEAREON feature slice.
                    </p>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
