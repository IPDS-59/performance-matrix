import type { ReviewStatus } from '@/types';

/** Review Bersama thresholds from the meeting mockup: >= 100 Tercapai, >= 70 Progres, else Rendah. */
export function reviewStatus(achievement: number | null): ReviewStatus {
    if (achievement === null) return 'none';
    if (achievement >= 100) return 'achieved';
    if (achievement >= 70) return 'progress';
    return 'low';
}

export const REVIEW_STATUS_META: Record<ReviewStatus, { label: string; chip: string }> = {
    achieved: { label: 'Tercapai', chip: 'bg-green-100 text-green-800' },
    progress: { label: 'Progres', chip: 'bg-amber-100 text-amber-800' },
    low: { label: 'Rendah', chip: 'bg-red-100 text-red-800' },
    none: { label: 'Belum ada data', chip: 'bg-gray-100 text-gray-600' },
};
