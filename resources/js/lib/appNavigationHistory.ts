import { router } from '@inertiajs/vue3';

const currentUrlKey = 'neareon.navigation.current-url';
const previousUrlKey = 'neareon.navigation.previous-url';

const internalUrl = (value: string): string | null => {
    try {
        const url = new URL(value, window.location.origin);

        if (url.origin !== window.location.origin) {
            return null;
        }

        return `${url.pathname}${url.search}${url.hash}`;
    } catch {
        return null;
    }
};

const currentLocation = (): string =>
    `${window.location.pathname}${window.location.search}${window.location.hash}`;

export function initializeAppNavigationHistory(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const current = currentLocation();
    const referrer = document.referrer ? internalUrl(document.referrer) : null;

    sessionStorage.setItem(currentUrlKey, current);

    if (referrer !== null && referrer !== current) {
        sessionStorage.setItem(previousUrlKey, referrer);
    } else {
        sessionStorage.removeItem(previousUrlKey);
    }

    router.on('navigate', (event) => {
        const pageUrl = (event as CustomEvent).detail?.page?.url;
        const next = typeof pageUrl === 'string' ? internalUrl(pageUrl) : null;

        if (next === null) {
            return;
        }

        const storedCurrent = sessionStorage.getItem(currentUrlKey);

        if (storedCurrent !== null && storedCurrent !== next) {
            sessionStorage.setItem(previousUrlKey, storedCurrent);
        }

        sessionStorage.setItem(currentUrlKey, next);
    });
}

export function navigateBack(fallback: string): void {
    const current = currentLocation();
    const previous = sessionStorage.getItem(previousUrlKey);
    const normalizedFallback = internalUrl(fallback);
    const hasInternalHistory =
        previous !== null && previous !== current && window.history.length > 1;

    if (hasInternalHistory) {
        window.history.back();

        return;
    }

    if (normalizedFallback === null || normalizedFallback === current) {
        return;
    }

    router.visit(normalizedFallback, {
        replace: true,
    });
}
