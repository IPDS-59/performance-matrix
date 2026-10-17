import type { PlanItemRow } from '@/types';

/** Capaian against target as a whole percent, or null when either number is missing or the target is 0. */
export function achievementPercent(capaian: number | null | undefined, target: number | null | undefined): number | null {
    if (capaian == null || !target) return null;
    return Math.round((capaian / target) * 100);
}

/** "9 blok" or "9,5 blok"; the unit is left out when empty. */
export function withUnit(value: number | null | undefined, unit: string | null | undefined): string {
    if (value == null) return '–';
    return [value.toLocaleString('id-ID'), unit].filter(Boolean).join(' ');
}

/** The dates of a kegiatan as one short text; a clock time follows a one-day kegiatan. */
export function periodText(plan: Pick<PlanItemRow, 'date_start' | 'date_end' | 'time_start' | 'time_end'>, format: (d: string) => string): string {
    const days = plan.date_start === plan.date_end ? format(plan.date_start) : `${format(plan.date_start)} – ${format(plan.date_end)}`;
    return plan.time_start && plan.time_end ? `${days}, ${plan.time_start}–${plan.time_end}` : days;
}
