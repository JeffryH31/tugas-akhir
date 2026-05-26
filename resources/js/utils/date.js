/**
 * Shared date/datetime formatters.
 *
 * Pages can override `locale` if needed; defaults are picked to match the
 * historical formats used across the codebase.
 */

const SHORT_OPTIONS = { day: 'numeric', month: 'short', year: 'numeric' };
const LONG_OPTIONS = { day: '2-digit', month: 'short', year: 'numeric' };
const DATETIME_OPTIONS = {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
};

export function formatDate(value, locale = 'en-US') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleDateString(locale, SHORT_OPTIONS);
}

export function formatDateLong(value, locale = 'en-GB') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleDateString(locale, LONG_OPTIONS);
}

export function formatDateTime(value, locale = 'id-ID') {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString(locale, DATETIME_OPTIONS);
}
