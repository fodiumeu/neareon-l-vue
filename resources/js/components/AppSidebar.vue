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
import {
    footerNavItems,
    getMainNavItems,
} from '@/config/navigation/app-navigation';
import { dashboard } from '@/routes';
import type { NavItem, User } from '@/types';

const page = usePage<{
    auth: {
        user: User | null;
    };
    contactRequests: {
        pendingReceivedCount: number;
    };
    project: {
        adminLabel: string;
        showAdminArea: boolean;
    };
}>();

const filterItemsByUserAccess = (items: NavItem[]): NavItem[] => {
    const user = page.props.auth.user;

    return items.filter((item) => {
        if (item.requiresAdmin) {
            return user?.role === 'admin';
        }

        return true;
    });
};

const visibleMainNavItems = computed(() =>
    filterItemsByUserAccess(
        getMainNavItems({
            adminLabel: page.props.project.adminLabel,
            pendingContactRequestsCount:
                page.props.contactRequests.pendingReceivedCount,
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
            <NavMain :items="visibleMainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="visibleFooterNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
