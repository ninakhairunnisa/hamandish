// Relative + absolute Persian time formatting.

export function timeAgo(iso) {
    if (!iso) return '';
    const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (seconds < 60) return 'لحظاتی پیش';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} دقیقه پیش`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} ساعت پیش`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days} روز پیش`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months} ماه پیش`;
    return `${Math.floor(months / 12)} سال پیش`;
}

export function fullDate(iso) {
    if (!iso) return '';
    return new Intl.DateTimeFormat('fa-IR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}
