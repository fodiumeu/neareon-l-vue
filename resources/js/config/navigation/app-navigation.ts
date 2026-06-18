import {
    ContactRound,
    Inbox,
    LayoutGrid,
    Search,
    Send,
    Settings,
    Shield,
    UserCircle,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { edit as editSettingsProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

type ProjectNavigationOptions = {
    adminLabel: string;
    pendingContactRequestsCount: number;
    showAdminArea: boolean;
};

export const getMainNavItems = ({
    adminLabel,
    pendingContactRequestsCount,
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
        title: 'Kontakte',
        href: '/contacts',
        icon: ContactRound,
    },
    {
        title: 'Kontaktanfragen',
        href: '/contact-requests',
        icon: Inbox,
        badge: pendingContactRequestsCount,
    },
    {
        title: 'Gesendete Anfragen',
        href: '/contact-requests/sent',
        icon: Send,
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
