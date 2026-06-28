import {
    Bell,
    CalendarDays,
    ContactRound,
    CircleSlash2,
    Database,
    Flag,
    FolderCog,
    Inbox,
    Languages,
    LayoutGrid,
    MessageCircle,
    Search,
    Send,
    ServerCog,
    Settings,
    Shield,
    Tags,
    UserCircle,
    Users,
} from 'lucide-vue-next';
import { dashboard } from '@/routes';
import { edit as editSettingsProfile } from '@/routes/profile';
import type { NavGroup, NavItem } from '@/types';

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

export const getMainNavGroups = ({
    adminLabel,
    pendingContactRequestsCount,
    pulseContactRequests,
    pulseMessages,
    pulseNotifications,
    unreadNotificationsCount,
    unreadMessagesCount,
    showAdminArea,
}: ProjectNavigationOptions): NavGroup[] => [
    {
        title: 'Hauptbereich',
        items: [
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
                title: 'Gruppen entdecken',
                href: '/groups',
                icon: Users,
            },
            {
                title: 'Events entdecken',
                href: '/events',
                icon: CalendarDays,
            },
        ],
    },
    {
        title: 'Community',
        items: [
            {
                title: 'Übersicht',
                href: '/community',
                icon: LayoutGrid,
            },
            {
                title: 'Meine Gruppen',
                href: '/my-groups',
                icon: Users,
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
                title: 'Ich folge',
                href: '/following',
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
        ],
    },
    {
        title: 'Kommunikation',
        items: [
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
                    unreadNotificationsCount >= 100
                        ? '99+'
                        : unreadNotificationsCount,
                pulseBadge: pulseNotifications,
            },
        ],
    },
    {
        title: 'Profil & Konto',
        items: [
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
        ],
    },
    ...(showAdminArea
        ? [
              {
                  title: 'Admin',
                  items: [
                      {
                          title: adminLabel,
                          href: '/admin',
                          icon: Shield,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Nutzer / Rollen',
                          href: '/admin#benutzer',
                          icon: Users,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Moderation / Reports',
                          href: '/admin/reports',
                          icon: Flag,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Stammdaten',
                          href: '/admin/options',
                          icon: Database,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Sprachen',
                          href: '/admin/options/languages',
                          icon: Languages,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Interessen',
                          href: '/admin/options/interests',
                          icon: Tags,
                          requiresAdmin: true,
                      },
                      {
                          title: 'System',
                          href: '/admin/system',
                          icon: ServerCog,
                          requiresAdmin: true,
                      },
                      {
                          title: 'Projekt',
                          href: '/admin/project',
                          icon: FolderCog,
                          requiresAdmin: true,
                      },
                  ],
              } satisfies NavGroup,
          ]
        : []),
];

export const getMainNavItems = (options: ProjectNavigationOptions): NavItem[] =>
    getMainNavGroups(options).flatMap((group) => group.items);

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
        title: 'Community',
        href: '/community',
        icon: ContactRound,
    },
    {
        title: 'Nachrichten',
        href: '/messages',
        icon: MessageCircle,
    },
    {
        title: 'Profil',
        href: '/profile',
        icon: UserCircle,
    },
];
