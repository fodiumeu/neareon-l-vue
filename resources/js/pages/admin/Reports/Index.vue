<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AdminNavigation from '@/components/AdminNavigation.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type ReportUser = {
    id: number;
    name: string;
    email: string;
};

type Report = {
    id: number;
    created_at: string;
    reporter: ReportUser;
    reported_user: ReportUser;
    reason: string;
    reason_label: string;
    description: string | null;
    status: 'open' | 'closed';
};

defineProps<{
    reports: Report[];
}>();

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Meldungen',
                href: '/admin/reports',
            },
        ],
    },
});
</script>

<template>
    <Head title="Meldungen" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Meldungen"
            description="Von Benutzern eingereichte Meldungen prüfen und ihren Status verwalten."
        />

        <AdminNavigation />

        <PageSection>
            <Card v-if="reports.length === 0">
                <CardContent>
                    <p class="py-8 text-center text-sm text-muted-foreground">
                        Es liegen keine Meldungen vor.
                    </p>
                </CardContent>
            </Card>

            <div v-else class="space-y-4">
                <Card v-for="report in reports" :key="report.id">
                    <CardHeader>
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <CardTitle>{{ report.reason_label }}</CardTitle>
                                <CardDescription class="mt-1">
                                    {{ formatDate(report.created_at) }}
                                </CardDescription>
                            </div>
                            <Badge
                                :variant="
                                    report.status === 'open'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{
                                    report.status === 'open'
                                        ? 'Offen'
                                        : 'Geschlossen'
                                }}
                            </Badge>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-5">
                        <dl class="grid gap-4 text-sm md:grid-cols-2">
                            <div>
                                <dt class="text-muted-foreground">Melder</dt>
                                <dd class="mt-1">
                                    <Link
                                        :href="`/admin/users/${report.reporter.id}`"
                                        class="font-medium underline decoration-transparent underline-offset-4 hover:decoration-current"
                                    >
                                        {{ report.reporter.name }}
                                    </Link>
                                    <span class="block text-muted-foreground">
                                        {{ report.reporter.email }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Gemeldeter Benutzer
                                </dt>
                                <dd class="mt-1">
                                    <Link
                                        :href="`/admin/users/${report.reported_user.id}`"
                                        class="font-medium underline decoration-transparent underline-offset-4 hover:decoration-current"
                                    >
                                        {{ report.reported_user.name }}
                                    </Link>
                                    <span class="block text-muted-foreground">
                                        {{ report.reported_user.email }}
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h2 class="text-sm font-medium">Beschreibung</h2>
                            <p
                                class="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground"
                            >
                                {{
                                    report.description ??
                                    'Keine Beschreibung angegeben.'
                                }}
                            </p>
                        </div>

                        <Form
                            :action="`/admin/reports/${report.id}/status`"
                            method="patch"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="secondary"
                                :disabled="processing"
                            >
                                <Spinner v-if="processing" />
                                {{
                                    report.status === 'open'
                                        ? 'Schließen'
                                        : 'Wieder öffnen'
                                }}
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
