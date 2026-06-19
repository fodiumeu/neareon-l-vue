import { onBeforeUnmount, onMounted, reactive, watch } from 'vue';

export type NavigationBadgeCounts = {
    unreadMessages: number;
    unreadNotifications: number;
    pendingContactRequests: number;
};

type BadgeKey = keyof NavigationBadgeCounts;

const pollingInterval = 30_000;
const pulseDuration = 1_000;

export function useLiveBadgePolling(
    source: () => NavigationBadgeCounts,
    enabled: () => boolean,
) {
    const counts = reactive<NavigationBadgeCounts>({ ...source() });
    const pulsing = reactive<Record<BadgeKey, boolean>>({
        unreadMessages: false,
        unreadNotifications: false,
        pendingContactRequests: false,
    });
    const pulseTimers = new Map<BadgeKey, ReturnType<typeof setTimeout>>();
    let interval: ReturnType<typeof setInterval> | null = null;
    let request: AbortController | null = null;

    const pulse = (key: BadgeKey) => {
        const existingTimer = pulseTimers.get(key);

        if (existingTimer !== undefined) {
            clearTimeout(existingTimer);
        }

        pulsing[key] = false;
        requestAnimationFrame(() => {
            pulsing[key] = true;
            pulseTimers.set(
                key,
                setTimeout(() => {
                    pulsing[key] = false;
                    pulseTimers.delete(key);
                }, pulseDuration),
            );
        });
    };

    const applyCounts = (next: NavigationBadgeCounts, animate: boolean) => {
        (Object.keys(next) as BadgeKey[]).forEach((key) => {
            if (animate && next[key] > counts[key]) {
                pulse(key);
            }

            counts[key] = next[key];
        });
    };

    const poll = async () => {
        if (!enabled() || document.visibilityState !== 'visible') {
            return;
        }

        request?.abort();
        request = new AbortController();

        try {
            const response = await fetch('/navigation/badges', {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                signal: request.signal,
            });

            if (!response.ok) {
                return;
            }

            applyCounts((await response.json()) as NavigationBadgeCounts, true);
        } catch (error) {
            if (
                !(error instanceof DOMException && error.name === 'AbortError')
            ) {
                console.error('Navigation badges could not be updated.', error);
            }
        }
    };

    const stop = () => {
        if (interval !== null) {
            clearInterval(interval);
            interval = null;
        }

        request?.abort();
        request = null;
    };

    const start = (pollImmediately = false) => {
        stop();

        if (!enabled() || document.visibilityState !== 'visible') {
            return;
        }

        if (pollImmediately) {
            void poll();
        }

        interval = setInterval(() => {
            void poll();
        }, pollingInterval);
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            start(true);
        } else {
            stop();
        }
    };

    watch(source, (next) => applyCounts(next, false));
    watch(enabled, (isEnabled) => {
        if (isEnabled) {
            start();
        } else {
            stop();
        }
    });

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        start();
    });

    onBeforeUnmount(() => {
        document.removeEventListener(
            'visibilitychange',
            handleVisibilityChange,
        );
        stop();
        pulseTimers.forEach((timer) => clearTimeout(timer));
    });

    return {
        counts,
        pulsing,
    };
}
