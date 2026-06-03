const SHORT_OPTS: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
const LONG_OPTS: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'long', year: 'numeric' };

/**
 * Date formatting helpers. Accepts either a plain `YYYY-MM-DD` string or a full
 * ISO timestamp; plain dates are parsed at local midnight to avoid timezone drift.
 */
export function useDateFormat(locale = 'id-ID') {
    function toDate(value: string): Date {
        return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);
    }

    function formatDate(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = SHORT_OPTS): string {
        if (!value) return '';
        const d = toDate(value);
        return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleDateString(locale, opts);
    }

    function formatWeekRange(start: string, end: string): string {
        return `${formatDate(start, LONG_OPTS)} — ${formatDate(end, LONG_OPTS)}`;
    }

    return { formatDate, formatWeekRange };
}
