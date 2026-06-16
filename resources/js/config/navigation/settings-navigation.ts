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
        title: 'General',
        href: '/settings/general',
    },
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
    ...(showAppearanceSettings
        ? [
              {
                  title: 'Appearance',
                  href: editAppearance(),
              } satisfies NavItem,
          ]
        : []),
];
