/** Number in the Indonesian style: 1.293 and 1.293,5, up to two decimals. "-" when there is no number. Accepts the strings Laravel sends for decimals. */
export function formatNumber(value: unknown): string {
    if (value === null || value === undefined || value === '') return '-';
    const n = Number(value);
    return Number.isFinite(n) ? n.toLocaleString("id-ID", { maximumFractionDigits: 2 }) : String(value);
}
