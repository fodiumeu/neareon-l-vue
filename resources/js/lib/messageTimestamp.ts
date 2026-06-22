const sameCalendarDay = (left: Date, right: Date) =>
    left.getFullYear() === right.getFullYear() &&
    left.getMonth() === right.getMonth() &&
    left.getDate() === right.getDate();

const startOfWeek = (date: Date) => {
    const start = new Date(date);
    const daysSinceMonday = (start.getDay() + 6) % 7;

    start.setHours(0, 0, 0, 0);
    start.setDate(start.getDate() - daysSinceMonday);

    return start;
};

export const formatMessageTimestamp = (
    value: string,
    now = new Date(),
): string => {
    const date = new Date(value);

    if (sameCalendarDay(date, now)) {
        return new Intl.DateTimeFormat('de-DE', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (sameCalendarDay(date, yesterday)) {
        return 'Gestern';
    }

    if (date >= startOfWeek(now)) {
        return new Intl.DateTimeFormat('de-DE', {
            weekday: 'long',
        }).format(date);
    }

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};

export const formatMessageTimestampTitle = (value: string): string =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
