<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Search, Users } from 'lucide-vue-next';
import PageHeader from '@/components/PageHeader.vue';
import PageSection from '@/components/PageSection.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

const exploreItems = [
    {
        title: 'Mitglieder entdecken',
        description:
            'Finde Mitglieder aus deiner Region und mit passenden Interessen.',
        href: '/discover',
        cta: 'Mitglieder entdecken',
        icon: Users,
    },
    {
        title: 'Gruppen entdecken',
        description: 'Entdecke regionale und thematische Gruppen.',
        href: '/groups',
        cta: 'Gruppen entdecken',
        icon: Search,
    },
    {
        title: 'Events entdecken',
        description: 'Finde Events und Aktivitäten aus deiner Community.',
        href: '/events',
        cta: 'Events entdecken',
        icon: CalendarDays,
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Entdecken',
                href: '/explore',
            },
        ],
    },
});
</script>

<template>
    <Head title="Entdecken" />

    <div
        class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6"
    >
        <PageHeader
            title="Entdecken"
            description="Finde Mitglieder, Gruppen und Events aus der NEAREON-Community."
        />

        <PageSection>
            <div class="grid min-w-0 gap-4 md:grid-cols-3">
                <Card
                    v-for="item in exploreItems"
                    :key="item.href"
                    class="min-w-0 border-border/80 bg-card/95 shadow-lg shadow-black/10 dark:shadow-black/30"
                >
                    <CardContent class="flex h-full min-w-0 flex-col gap-5 p-5">
                        <div
                            class="flex size-11 items-center justify-center rounded-full border border-primary/25 bg-primary/10 text-primary"
                            aria-hidden="true"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>

                        <div class="min-w-0 space-y-2">
                            <h2 class="text-base font-semibold">
                                {{ item.title }}
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>

                        <Button as-child class="mt-auto w-full">
                            <Link :href="item.href">{{ item.cta }}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageSection>
    </div>
</template>
