<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InterestOptionForm from '@/components/admin/InterestOptionForm.vue';
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
                    <CardTitle>Interesse anlegen</CardTitle>
                    <CardDescription>
                        Ergänze eine neue Interessenoption für den zentralen
                        NEAREON-Katalog.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <InterestOptionForm
                        action="/admin/options/interests"
                        id-prefix="create-interest"
                        method="post"
                        submit-label="Interesse anlegen"
                        show-active-field
                    />
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Interessenübersicht</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Bearbeite Bezeichnungen und Reihenfolge oder ändere den
                        Aktivstatus.
                    </p>
                </div>

                <Card v-if="interests.length === 0">
                    <CardContent>
                        <div
                            class="rounded-md border border-dashed border-border bg-background/50 px-4 py-8 text-center dark:bg-input/20"
                        >
                            <h2 class="text-sm font-semibold">
                                Keine Interessenoptionen vorhanden
                            </h2>
                            <p
                                class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                            >
                                Lege die erste Interessenoption über das
                                Formular oberhalb dieser Übersicht an.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div v-else class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-for="interest in interests"
                        :key="interest.id"
                        class="bg-card/95"
                    >
                        <CardHeader>
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <CardTitle>{{ interest.label }}</CardTitle>
                                    <CardDescription class="mt-1 break-all">
                                        {{ interest.slug }}
                                    </CardDescription>
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
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <dl
                                class="rounded-md border border-border bg-background/60 p-3 text-sm dark:bg-input/20"
                            >
                                <div>
                                    <dt class="text-muted-foreground">
                                        Sortierung
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {{ interest.sort_order }}
                                    </dd>
                                </div>
                            </dl>

                            <Form
                                :action="`/admin/options/interests/${interest.id}/status`"
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
                                        interest.is_active
                                            ? 'Deaktivieren'
                                            : 'Aktivieren'
                                    }}
                                </Button>
                            </Form>

                            <details
                                class="rounded-md border border-border bg-background/40 open:bg-background/70 dark:bg-input/15 dark:open:bg-input/25"
                            >
                                <summary
                                    class="cursor-pointer px-4 py-3 text-sm font-medium"
                                >
                                    Interesse bearbeiten
                                </summary>
                                <div class="border-t border-border p-4">
                                    <InterestOptionForm
                                        :action="`/admin/options/interests/${interest.id}`"
                                        :id-prefix="`interest-${interest.id}`"
                                        method="patch"
                                        submit-label="Änderungen speichern"
                                        :interest="interest"
                                    />
                                </div>
                            </details>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageSection>
    </div>
</template>
