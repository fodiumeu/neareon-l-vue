import { LayoutGrid, Shield } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

type ProjectNavigationOptions = {
    adminLabel: string;
    showAdminArea: boolean;
};

export const getMainNavItems = ({
    adminLabel,
    showAdminArea,
}: ProjectNavigationOptions): NavItem[] => [
    {
        title: 'Home',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(showAdminArea
        ? [
              {
                  title: adminLabel,
                  href: '/admin',
                  icon: Shield,
                  requiresAdmin: true,
              } satisfies NavItem,
          ]
        : []),
];

export const footerNavItems: NavItem[] = [];
