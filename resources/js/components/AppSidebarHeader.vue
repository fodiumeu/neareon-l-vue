<script setup lang="ts">
import { computed } from 'vue';
import AppBackButton from '@/components/AppBackButton.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { mobileHeaderBackFor } from '@/config/navigation/mobile-header-navigation';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { currentUrl } = useCurrentUrl();
const mobileBack = computed(() => mobileHeaderBackFor(currentUrl.value));
const pageName = computed(() => props.breadcrumbs.at(-1)?.title ?? 'NEAREON');
</script>

<template>
    <header
        class="sticky top-0 z-30 flex min-h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/90 px-4 shadow-sm backdrop-blur md:hidden"
        data-test="mobile-sticky-header"
    >
        <AppBackButton
            v-if="mobileBack"
            :fallback="mobileBack.fallback"
            :label="mobileBack.label"
            class="-ml-2 shrink-0"
        />
        <SidebarTrigger v-else class="-ml-1 shrink-0" />

        <span class="min-w-0 truncate text-sm font-semibold">
            {{ pageName }}
        </span>
    </header>

    <header
        class="hidden shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear md:flex md:px-4"
        style="height: var(--app-shell-header-height)"
        :class="'group-has-data-[collapsible=icon]/sidebar-wrapper:[height:var(--app-shell-header-height-collapsed)]'"
        data-test="desktop-app-header"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
    </header>
</template>
