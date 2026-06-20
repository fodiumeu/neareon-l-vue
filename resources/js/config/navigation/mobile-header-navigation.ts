export type MobileHeaderBack = {
    fallback: string;
    label: string;
};

export function mobileHeaderBackFor(path: string): MobileHeaderBack | null {
    if (path === '/profile/edit') {
        return {
            fallback: '/profile',
            label: 'Zurück',
        };
    }

    if (/^\/u\/[^/]+$/.test(path)) {
        return {
            fallback: '/discover',
            label: 'Zurück',
        };
    }

    if (/^\/messages\/[^/]+$/.test(path)) {
        return {
            fallback: '/messages',
            label: 'Zurück',
        };
    }

    if (/^\/admin\/users\/[^/]+$/.test(path)) {
        return {
            fallback: '/admin#benutzer',
            label: 'Zurück',
        };
    }

    return null;
}
