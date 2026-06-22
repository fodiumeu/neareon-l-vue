const sameCalendarDay = (left: Date, right: Date) =>
    left.getFullYear() === right.getFullYear() &&
    left.getMonth() === right.getMonth() &&
    left.getDate() === right.getDate();

export const formatContactRelativeTime = (
    value: string,
    now = new Date(),
): string => {
    const date = new Date(value);
    const differenceInMinutes = Math.max(
        0,
        Math.floor((now.getTime() - date.getTime()) / 60_000),
    );

    if (differenceInMinutes < 1) {
        return 'heute';
    }

    if (differenceInMinutes < 60) {
        return `vor ${differenceInMinutes} ${
            differenceInMinutes === 1 ? 'Minute' : 'Minuten'
        }`;
    }

    if (sameCalendarDay(date, now)) {
        const hours = Math.floor(differenceInMinutes / 60);

        return `vor ${hours} ${hours === 1 ? 'Stunde' : 'Stunden'}`;
    }

    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (sameCalendarDay(date, yesterday)) {
        return 'gestern';
    }

    const dateDay = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const nowDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const days = Math.max(
        1,
        Math.round((nowDay.getTime() - dateDay.getTime()) / 86_400_000),
    );

    return `vor ${days} ${days === 1 ? 'Tag' : 'Tagen'}`;
};

export const formatContactRelativeTimeTitle = (value: string): string =>
    new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
