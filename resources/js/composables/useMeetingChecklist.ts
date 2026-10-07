import type { ChecklistStep, RecapLockState, RecapSegment } from '@/types';

function lockStep(lock: RecapLockState | null): ChecklistStep {
    return {
        label: 'Kunci rekap',
        done: lock !== null,
        detail: lock ? 'Rekap sudah dikunci.' : 'Belum dikunci.',
        target: 'kunci-rekap',
    };
}

/** Monthly / quarterly meeting: every row signed off, then lock. */
export function periodChecklist(input: { segments: RecapSegment[]; lock: RecapLockState | null }): ChecklistStep[] {
    const rows = input.segments.flatMap(s => s.rows);
    const confirmed = rows.filter(r => r.is_confirmed).length;

    return [
        {
            label: 'Semua baris dikonfirmasi',
            done: rows.length > 0 && confirmed === rows.length,
            detail: rows.length ? `${confirmed} dari ${rows.length} baris dikonfirmasi.` : 'Belum ada klaim tersimpan pada periode ini.',
            target: 'rekap-baris',
        },
        lockStep(input.lock),
    ];
}
