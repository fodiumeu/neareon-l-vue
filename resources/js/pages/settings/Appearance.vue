<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import PageSection from '@/components/PageSection.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Darstellung',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Darstellung" />

    <h1 class="sr-only">Darstellung</h1>

    <PageSection>
        <Heading
            variant="small"
            title="Darstellung"
            description="Passe die Darstellung deines Accounts an"
        />
        <AppearanceTabs />
    </PageSection>
</template>
