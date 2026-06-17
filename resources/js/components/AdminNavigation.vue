<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Database,
    FolderCog,
    Languages,
    LayoutDashboard,
    ServerCog,
    Tags,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn } from '@/lib/utils';

const { currentUrl } = useCurrentUrl();

const items = [
    {
        title: 'Übersicht',
        href: '/admin',
        icon: LayoutDashboard,
        active: () => currentUrl.value === '/admin',
    },
    {
        title: 'Benutzer',
        href: '/admin#benutzer',
        icon: Users,
        active: () => currentUrl.value.startsWith('/admin/users'),
    },
    {
        title: 'Stammdaten',
        href: '/admin/options',
        icon: Database,
        active: () => currentUrl.value === '/admin/options',
    },
    {
        title: 'Sprachen',
        href: '/admin/options/languages',
        icon: Languages,
        active: () => currentUrl.value === '/admin/options/languages',
        nested: true,
    },
    {
        title: 'Interessen',
        href: '/admin/options/interests',
        icon: Tags,
        active: () => currentUrl.value === '/admin/options/interests',
        nested: true,
    },
    {
        title: 'System',
        href: '/admin/system',
        icon: ServerCog,
        active: () => currentUrl.value === '/admin/system',
    },
    {
        title: 'Projekt',
        href: '/admin/project',
        icon: FolderCog,
        active: () => currentUrl.value === '/admin/project',
    },
];

const visibleItems = computed(() => items);
</script>

<template>
    <nav aria-label="Admin-Navigation" class="border-b border-border/80 pb-4">
        <div class="flex gap-2 overflow-x-auto pb-1">
            <Link
                v-for="item in visibleItems"
                :key="item.href"
                :href="item.href"
                :aria-current="item.active() ? 'page' : undefined"
                :class="
                    cn(
                        'flex min-h-10 shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                        item.nested && 'ml-2 border-l border-border pl-3',
                        item.active() &&
                            'bg-primary/15 text-primary hover:bg-primary/20 hover:text-primary',
                    )
                "
            >
                <component :is="item.icon" class="size-4" />
                <span>{{ item.title }}</span>
            </Link>
        </div>
    </nav>
</template>
