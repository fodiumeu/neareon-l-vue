<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import PageSection from '@/components/PageSection.vue';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

type GeneralSettingsPageProps = {
    app: {
        name: string;
    };
    project: {
        tagline: string;
        dashboardTitle: string;
    };
    auth: {
        user: {
            email: string;
            role: string;
        };
    };
};

const page = usePage<GeneralSettingsPageProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'General settings',
                href: '/settings/general',
            },
        ],
    },
});
</script>

<template>
    <Head title="General settings" />

    <h1 class="sr-only">General settings</h1>

    <PageSection>
        <Heading
            variant="small"
            title="General settings"
            description="A small read-only overview of core account and project context. Update actions remain in their dedicated settings areas."
        />

        <div class="space-y-3">
            <div class="rounded-lg border border-border px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    App name
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ page.props.app.name }}
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Email address
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ page.props.auth.user.email }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    Update your email in
                    <Link
                        :href="editProfile()"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                    >
                        Profile settings
                    </Link>
                    .
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Role
                </p>
                <p class="mt-1 text-sm font-medium capitalize">
                    {{ page.props.auth.user.role }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    Password and sign-in security can be managed in
                    <Link
                        :href="editSecurity()"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                    >
                        Security settings
                    </Link>
                    .
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Tagline
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ page.props.project.tagline }}
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Dashboard title
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ page.props.project.dashboardTitle }}
                </p>
            </div>
        </div>
    </PageSection>
</template>
