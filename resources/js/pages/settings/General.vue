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
                title: 'Allgemeine Einstellungen',
                href: '/settings/general',
            },
        ],
    },
});
</script>

<template>
    <Head title="Allgemeine Einstellungen" />

    <h1 class="sr-only">Allgemeine Einstellungen</h1>

    <PageSection>
        <Heading
            variant="small"
            title="Allgemeine Einstellungen"
            description="Ein kurzer Überblick über Account- und Projektkontext. Änderungen bleiben in den jeweiligen Einstellungsbereichen."
        />

        <div class="space-y-3">
            <div class="rounded-lg border border-border px-4 py-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    App-Name
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ page.props.app.name }}
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    E-Mail-Adresse
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ page.props.auth.user.email }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    Ändere deine E-Mail-Adresse in den
                    <Link
                        :href="editProfile()"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                    >
                        Profileinstellungen
                    </Link>
                    .
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Rolle
                </p>
                <p class="mt-1 text-sm font-medium capitalize">
                    {{ page.props.auth.user.role }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    Passwort und Anmeldesicherheit verwaltest du in den
                    <Link
                        :href="editSecurity()"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                    >
                        Sicherheitseinstellungen
                    </Link>
                    .
                </p>
            </div>

            <div class="rounded-lg border border-border px-4 py-3">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Tagline
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ page.props.project.tagline }}
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
        </div>
    </PageSection>
</template>
