<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import LanguageOptionForm from '@/components/admin/LanguageOptionForm.vue';
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

type LanguageOption = {
    id: number;
    code: string;
    label: string;
    native_label: string | null;
    sort_order: number;
    is_active: boolean;
};

defineProps<{
    languages: LanguageOption[];
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
                title: 'Sprachen',
                href: '/admin/options/languages',
            },
        ],
    },
});
</script>

<template>
    <Head title="Sprachen" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
        <PageHeader
            title="Sprachen"
            description="Zentrale Sprachoptionen für zukünftige Profil- und Onboarding-Funktionen."
        />

        <AdminNavigation />

        <PageSection>
            <Card>
                <CardHeader>
                    <CardTitle>Sprache anlegen</CardTitle>
                    <CardDescription>
                        Ergänze eine neue Sprachoption für den zentralen
                        NEAREON-Katalog.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <LanguageOptionForm
                        action="/admin/options/languages"
                        id-prefix="create-language"
                        method="post"
                        submit-label="Sprache anlegen"
                        show-active-field
                    />
                </CardContent>
            </Card>
        </PageSection>

        <PageSection>
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Sprachübersicht</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Bearbeite Bezeichnungen und Reihenfolge oder ändere den
                        Aktivstatus.
                    </p>
                </div>

                <Card v-if="languages.length === 0">
                    <CardContent>
                        <div
                            class="rounded-md border border-dashed border-border bg-background/50 px-4 py-8 text-center dark:bg-input/20"
                        >
                            <h2 class="text-sm font-semibold">
                                Keine Sprachoptionen vorhanden
                            </h2>
                            <p
                                class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                            >
                                Lege die erste Sprachoption über das Formular
                                oberhalb dieser Übersicht an.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div v-else class="grid gap-4 xl:grid-cols-2">
                    <Card
                        v-for="language in languages"
                        :key="language.id"
                        class="bg-card/95"
                    >
                        <CardHeader>
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <CardTitle>{{ language.label }}</CardTitle>
                                    <CardDescription class="mt-1">
                                        {{
                                            language.native_label ??
                                            'Keine native Bezeichnung'
                                        }}
                                    </CardDescription>
                                </div>

                                <Badge
                                    :variant="
                                        language.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        language.is_active ? 'Aktiv' : 'Inaktiv'
                                    }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <dl
                                class="grid grid-cols-2 gap-4 rounded-md border border-border bg-background/60 p-3 text-sm dark:bg-input/20"
                            >
                                <div>
                                    <dt class="text-muted-foreground">Code</dt>
                                    <dd class="mt-1 font-medium uppercase">
                                        {{ language.code }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground">
                                        Sortierung
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {{ language.sort_order }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="flex flex-wrap gap-3">
                                <Form
                                    :action="`/admin/options/languages/${language.id}/status`"
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
                                            language.is_active
                                                ? 'Deaktivieren'
                                                : 'Aktivieren'
                                        }}
                                    </Button>
                                </Form>
                            </div>

                            <details
                                class="rounded-md border border-border bg-background/40 open:bg-background/70 dark:bg-input/15 dark:open:bg-input/25"
                            >
                                <summary
                                    class="cursor-pointer px-4 py-3 text-sm font-medium"
                                >
                                    Sprache bearbeiten
                                </summary>
                                <div class="border-t border-border p-4">
                                    <LanguageOptionForm
                                        :action="`/admin/options/languages/${language.id}`"
                                        :id-prefix="`language-${language.id}`"
                                        method="patch"
                                        submit-label="Änderungen speichern"
                                        :language="language"
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
