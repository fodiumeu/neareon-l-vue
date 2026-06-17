<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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

type InterestOption = {
    id: number;
    slug: string;
    label: string;
    sort_order: number;
    is_active: boolean;
};

defineProps<{
    interests: InterestOption[];
}>();

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
            {
                title: 'Interessen',
                href: '/admin/options/interests',
            },
        ],
    },
});
</script>

<template>
    <Head title="Interessen" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Interessen"
            description="Zentrale Interessenoptionen für zukünftige Profil- und Onboarding-Funktionen."
        />

        <AdminNavigation />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>Interessenübersicht</CardTitle>
                    <CardDescription>
                        Aktuell konfigurierte Interessenoptionen in ihrer
                        festgelegten Reihenfolge.
                    </CardDescription>
                </CardHeader>
                <CardContent v-if="interests.length === 0">
                    <div
                        class="rounded-md border border-dashed border-border bg-background/50 px-4 py-8 text-center dark:bg-input/20"
                    >
                        <h2 class="text-sm font-semibold">
                            Keine Interessenoptionen vorhanden
                        </h2>
                        <p
                            class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                        >
                            Sobald Interessenoptionen angelegt oder eingespielt
                            wurden, erscheinen sie in dieser Übersicht.
                        </p>
                    </div>
                </CardContent>

                <CardContent v-else class="space-y-4">
                    <div class="grid gap-3 md:hidden">
                        <article
                            v-for="interest in interests"
                            :key="interest.id"
                            class="space-y-4 rounded-md border border-border bg-background/60 p-4 dark:bg-input/20"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <h2 class="font-semibold">
                                        {{ interest.label }}
                                    </h2>
                                    <p
                                        class="text-sm break-all text-muted-foreground"
                                    >
                                        {{ interest.slug }}
                                    </p>
                                </div>
                                <Badge
                                    :variant="
                                        interest.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        interest.is_active ? 'Aktiv' : 'Inaktiv'
                                    }}
                                </Badge>
                            </div>

                            <dl class="text-sm">
                                <div>
                                    <dt class="text-muted-foreground">
                                        Sortierung
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {{ interest.sort_order }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    </div>

                    <div
                        class="hidden overflow-hidden rounded-md border border-border md:block"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-2xl text-left text-sm">
                                <thead
                                    class="bg-muted/70 text-xs text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            Interesse
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Slug
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Sortierung
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="interest in interests"
                                        :key="interest.id"
                                        class="bg-background/50 dark:bg-input/15"
                                    >
                                        <td
                                            class="px-4 py-3 font-medium text-foreground"
                                        >
                                            {{ interest.label }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-muted-foreground"
                                        >
                                            {{ interest.slug }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ interest.sort_order }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :variant="
                                                    interest.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                            >
                                                {{
                                                    interest.is_active
                                                        ? 'Aktiv'
                                                        : 'Inaktiv'
                                                }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </PageSection>
    </div>
</template>
