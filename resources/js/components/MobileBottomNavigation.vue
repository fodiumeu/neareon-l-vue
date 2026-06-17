<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { mobileBottomNavItems } from '@/config/navigation/app-navigation';
import { cn, toUrl } from '@/lib/utils';

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const isVisible = computed(() => !currentUrl.value.startsWith('/onboarding'));

const isActiveItem = (href: (typeof mobileBottomNavItems)[number]['href']) => {
    const path = toUrl(href);

    if (path === '/profile') {
        return (
            isCurrentUrl('/profile') ||
            isCurrentUrl('/profile/edit') ||
            currentUrl.value.startsWith('/profile/')
        );
    }

    if (path.startsWith('/settings')) {
        return isCurrentOrParentUrl('/settings');
    }

    return isCurrentUrl(href);
};
</script>

<template>
    <nav
        v-if="isVisible"
        aria-label="Mobile Hauptnavigation"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-card/95 px-3 pt-2 pb-[calc(env(safe-area-inset-bottom)+0.5rem)] shadow-[0_-12px_30px_rgba(0,0,0,0.16)] backdrop-blur md:hidden dark:shadow-[0_-16px_36px_rgba(0,0,0,0.45)]"
    >
        <div class="mx-auto grid max-w-md grid-cols-4 gap-1">
            <Link
                v-for="item in mobileBottomNavItems"
                :key="item.title"
                :href="item.href"
                :aria-current="isActiveItem(item.href) ? 'page' : undefined"
                :class="
                    cn(
                        'flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-2 text-xs font-medium text-muted-foreground transition-colors',
                        isActiveItem(item.href)
                            ? 'bg-primary/15 text-primary'
                            : 'hover:bg-accent hover:text-accent-foreground',
                    )
                "
            >
                <component :is="item.icon" class="size-5" />
                <span class="max-w-full truncate">{{ item.title }}</span>
            </Link>
        </div>
    </nav>
</template>
