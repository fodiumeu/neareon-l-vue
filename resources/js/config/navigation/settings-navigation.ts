import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

type ProjectSettingsNavigationOptions = {
    showAppearanceSettings: boolean;
};

export const getSettingsNavItems = ({
    showAppearanceSettings,
}: ProjectSettingsNavigationOptions): NavItem[] => [
    {
        title: 'Allgemein',
        href: '/settings/general',
    },
    {
        title: 'Profil',
        href: editProfile(),
    },
    {
        title: 'Sicherheit',
        href: editSecurity(),
    },
    ...(showAppearanceSettings
        ? [
              {
                  title: 'Darstellung',
                  href: editAppearance(),
              } satisfies NavItem,
          ]
        : []),
];
