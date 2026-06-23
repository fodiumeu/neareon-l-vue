import {
    Bell,
    ContactRound,
    CircleSlash2,
    Inbox,
    LayoutGrid,
    MessageCircle,
    Search,
    Send,
    Settings,
    Shield,
    UserCircle,
    Users,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { edit as editSettingsProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

type ProjectNavigationOptions = {
    adminLabel: string;
    pendingContactRequestsCount: number;
    pulseContactRequests: boolean;
    pulseMessages: boolean;
    pulseNotifications: boolean;
    unreadNotificationsCount: number;
    unreadMessagesCount: number;
    showAdminArea: boolean;
};

export const getMainNavItems = ({
    adminLabel,
    pendingContactRequestsCount,
    pulseContactRequests,
    pulseMessages,
    pulseNotifications,
    unreadNotificationsCount,
    unreadMessagesCount,
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
        title: 'Follower',
        href: '/followers',
        icon: Users,
    },
    {
        title: 'Kontaktanfragen',
        href: '/contact-requests',
        icon: Inbox,
        badge: pendingContactRequestsCount,
        pulseBadge: pulseContactRequests,
    },
    {
        title: 'Gesendete Anfragen',
        href: '/contact-requests/sent',
        icon: Send,
    },
    {
        title: 'Blockierte Profile',
        href: '/blocked-profiles',
        icon: CircleSlash2,
    },
    {
        title: 'Nachrichten',
        href: '/messages',
        icon: MessageCircle,
        badge: unreadMessagesCount >= 100 ? '99+' : unreadMessagesCount,
        pulseBadge: pulseMessages,
    },
    {
        title: 'Benachrichtigungen',
        href: '/notifications',
        icon: Bell,
        badge:
            unreadNotificationsCount >= 100 ? '99+' : unreadNotificationsCount,
        pulseBadge: pulseNotifications,
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
