<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useLiveBadgePolling } from '@/composables/useLiveBadgePolling';
import {
    footerNavItems,
    getMainNavGroups,
} from '@/config/navigation/app-navigation';
import { dashboard } from '@/routes';
import type { NavGroup, NavItem, User } from '@/types';

const page = usePage<{
    auth: {
        user: User | null;
    };
    contactRequests: {
        pendingReceivedCount: number;
    };
    messages: {
        unreadCount: number;
    };
    notifications: {
        unreadCount: number;
    };
    project: {
        adminLabel: string;
        showAdminArea: boolean;
    };
}>();

const canAccessItem = (item: NavItem): boolean => {
    const user = page.props.auth.user;

    if (item.requiresAdmin) {
        return user?.role === 'admin' || user?.role === 'owner';
    }

    return true;
};

const filterItemsByUserAccess = (items: NavItem[]): NavItem[] =>
    items.filter(canAccessItem);

const filterGroupsByUserAccess = (groups: NavGroup[]): NavGroup[] => {
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(canAccessItem),
        }))
        .filter((group) => group.items.length > 0);
};

const { counts: liveBadgeCounts, pulsing: pulsingBadges } = useLiveBadgePolling(
    () => ({
        pendingContactRequests: page.props.contactRequests.pendingReceivedCount,
        unreadMessages: page.props.messages.unreadCount,
        unreadNotifications: page.props.notifications.unreadCount,
    }),
    () => page.props.auth.user !== null,
);

const visibleMainNavGroups = computed(() =>
    filterGroupsByUserAccess(
        getMainNavGroups({
            adminLabel: page.props.project.adminLabel,
            pendingContactRequestsCount: liveBadgeCounts.pendingContactRequests,
            pulseContactRequests: pulsingBadges.pendingContactRequests,
            pulseMessages: pulsingBadges.unreadMessages,
            pulseNotifications: pulsingBadges.unreadNotifications,
            unreadNotificationsCount: liveBadgeCounts.unreadNotifications,
            unreadMessagesCount: liveBadgeCounts.unreadMessages,
            showAdminArea: page.props.project.showAdminArea,
        }),
    ),
);
const visibleFooterNavItems = computed(() =>
    filterItemsByUserAccess(footerNavItems),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="visibleMainNavGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="visibleFooterNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
