import type { RecapClaimLine } from '@/types';

/** The editable numbers of one member claim, as text so an empty box stays empty. */
export type NumberForm = { target: string; realization: string; target_unit: string };

const str = (v: number | null) => (v == null ? '' : String(v));

export function numberFormOf(line: RecapClaimLine): NumberForm {
    return { target: str(line.target), realization: str(line.realization), target_unit: line.target_unit ?? '' };
}

/** Capaian in percent, null until a target above 0 and a realisasi are filled. */
export function achievementOf(target: number | string, realization: number | string): number | null {
    const t = Number(target);
    return target !== '' && t > 0 && realization !== '' ? (Number(realization) / t) * 100 : null;
}

export function achievementColor(v: number | null): string {
    if (v == null) return 'text-gray-400';
    if (v >= 100) return 'text-green-600';
    if (v >= 50) return 'text-orange-600';
    return 'text-red-600';
}

export const percentLabel = (v: number | null) => (v == null ? '—' : `${Math.round(v)}%`);

/** Sum of the edited numbers of several claims. */
export function totalsOf(claims: RecapClaimLine[], numbers: Record<number, NumberForm>) {
    const forms = claims.map(c => numbers[c.claim_id]).filter((f): f is NumberForm => !!f);
    const target = forms.reduce((n, f) => n + (Number(f.target) || 0), 0);
    const realization = forms.reduce((n, f) => n + (Number(f.realization) || 0), 0);
    return { target, realization, unit: forms.find(f => f.target_unit)?.target_unit ?? '', achievement: achievementOf(target, realization) };
}
