<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminNavigation from '@/components/AdminNavigation.vue';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin',
            },
            {
                title: 'Stammdaten',
                href: '/admin/options',
            },
        ],
    },
});
</script>

<template>
    <Head title="Stammdaten" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Stammdaten"
            description="Zentrale Auswahlwerte für NEAREON werden hier schrittweise verwaltet."
        />

        <AdminNavigation />

        <PageSection>
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Sprachen</CardTitle>
                        <CardDescription>
                            Grundlage für administrierbare Sprachoptionen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button as-child variant="secondary">
                            <Link href="/admin/options/languages">
                                Sprachen öffnen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Interessen</CardTitle>
                        <CardDescription>
                            Grundlage für administrierbare Interessenoptionen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button as-child variant="secondary">
                            <Link href="/admin/options/interests">
                                Interessen öffnen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
