import type { CreditStatus, CreditSummary } from '@/types';

export const CREDIT_STATUS_META: Record<CreditStatus, { label: string; chip: string }> = {
    ready: { label: 'Siap diusulkan', chip: 'bg-green-100 text-green-800' },
    ak_ready: { label: 'AK cukup, tunggu 2 tahun', chip: 'bg-teal-100 text-teal-800' },
    near: { label: 'Hampir', chip: 'bg-amber-100 text-amber-800' },
    progress: { label: 'Berjalan', chip: 'bg-blue-50 text-blue-700' },
    top: { label: 'Jenjang puncak', chip: 'bg-gray-100 text-gray-700' },
    no_data: { label: 'Data belum ada', chip: 'bg-gray-100 text-gray-500' },
    non_jf: { label: 'Bukan JF', chip: 'bg-gray-100 text-gray-500' },
};

const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });

/** Angka Kredit with Indonesian decimals, e.g. 23,438. */
export function formatAk(value: number | null | undefined): string {
    return value === null || value === undefined ? '—' : number.format(value);
}

/** Share of the target reached, 0–100. */
export function creditProgress(credit: CreditSummary): number {
    if (!credit.target || credit.earned === undefined) return 0;
    return Math.min(100, (credit.earned / credit.target) * 100);
}

/** "naik pangkat ke III/b" or "naik jenjang ke Ahli Muda". */
export function nextStepLabel(credit: CreditSummary): string | null {
    if (!credit.kind || !credit.next_label) return null;
    return credit.kind === 'pangkat' ? `naik pangkat ke ${credit.next_label}` : `naik jenjang ke ${credit.next_label}`;
}
