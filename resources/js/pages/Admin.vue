<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
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
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
};

defineProps<{
    users: AdminUser[];
}>();

const page = usePage<{
    project: {
        adminLabel: string;
    };
}>();

const roleLabels: Record<UserRole, string> = {
    member: 'Mitglied',
    moderator: 'Moderator',
    admin: 'Administrator',
    owner: 'Inhaber',
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
        ],
    },
});
</script>

<template>
    <Head :title="page.props.project.adminLabel" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            :title="page.props.project.adminLabel"
            description="Verwalte Benutzerkonten und prüfe die zentralen Projekt- und Systeminformationen."
        />

        <AdminNavigation />

        <PageSection id="benutzer">
            <div class="grid gap-6 xl:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Benutzer</CardTitle>
                        <CardDescription>
                            Übersicht der vorhandenen Benutzerkonten und Rollen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            class="flex flex-col gap-4 rounded-lg border border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 space-y-2">
                                <Link
                                    :href="`/admin/users/${user.id}`"
                                    class="block truncate text-sm font-medium underline decoration-transparent underline-offset-4 transition-colors hover:decoration-current"
                                >
                                    {{ user.name }}
                                </Link>
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </div>
                            <div class="flex items-center sm:justify-end">
                                <Badge variant="secondary">
                                    {{ roleLabels[user.role] }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Projektübersicht</CardTitle>
                        <CardDescription>
                            Aktuelle Konfiguration und freigegebene
                            Projekteinstellungen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Prüfe die gemeinsamen App- und Projektwerte der
                            Plattform.
                        </p>
                        <Link
                            href="/admin/project"
                            class="inline-flex items-center text-sm font-medium underline decoration-transparent underline-offset-4 transition-colors hover:decoration-current"
                        >
                            Projektübersicht öffnen
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Systemstatus</CardTitle>
                        <CardDescription>
                            Laufzeit-, Framework- und Statusinformationen der
                            Anwendung.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Prüfe Umgebung, Framework und zentrale
                            Konfigurationswerte.
                        </p>
                        <Link
                            href="/admin/system"
                            class="inline-flex items-center text-sm font-medium underline decoration-transparent underline-offset-4 transition-colors hover:decoration-current"
                        >
                            Systemstatus öffnen
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
