import axios from 'axios';
import type { PresensiRow } from '@/types';

/** Presensi of the quarter of `date`, from kipApp through Kinetik. Throws an Error with a message for the user. */
export async function loadPresensi(date: string): Promise<PresensiRow[]> {
    try {
        const { data } = await axios.get<{ data: PresensiRow[] }>(route('plan-items.presensi'), { params: { date } });
        return sortPresensi(data.data ?? []);
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        throw new Error(message ?? 'Presensi dari kipApp belum bisa diambil. Coba lagi nanti.');
    }
}

/** Oldest first, like kipApp's list. */
export function sortPresensi(rows: PresensiRow[]): PresensiRow[] {
    return [...rows].sort((a, b) => a.date.localeCompare(b.date));
}

export function filterPresensi(rows: PresensiRow[], query: string): PresensiRow[] {
    const q = query.trim().toLowerCase();
    return q ? rows.filter(r => `${r.date} ${r.description}`.toLowerCase().includes(q)) : rows;
}
