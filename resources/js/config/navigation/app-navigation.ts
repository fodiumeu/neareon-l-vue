import {
    LayoutGrid,
    Search,
    Settings,
    Shield,
    UserCircle,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { edit as editSettingsProfile } from '@/routes/profile';
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
    {
        title: 'Entdecken',
        href: '/discover',
        icon: Search,
    },
    {
        title: 'Profil',
        href: '/profile',
        icon: UserCircle,
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

export const mobileBottomNavItems: NavItem[] = [
    {
        title: 'Home',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Entdecken',
        href: '/discover',
        icon: Search,
    },
    {
        title: 'Profil',
        href: '/profile',
        icon: UserCircle,
    },
    {
        title: 'Einstellungen',
        href: editSettingsProfile(),
        icon: Settings,
    },
];
