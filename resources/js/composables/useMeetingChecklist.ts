import type { ChecklistStep, MemberCompleteness, RecapLockState, RecapSegment, TeamRecapEvidence, WeeklyTeamNote } from '@/types';

function lockStep(lock: RecapLockState | null): ChecklistStep {
    return {
        label: 'Kunci rekap',
        done: lock !== null,
        detail: lock ? 'Rekap sudah dikunci.' : 'Belum dikunci.',
        target: 'kunci-rekap',
    };
}

/** Weekly meeting (old "Rapat Mingguan"): members in, PJ summary, evidence, lock. */
export function weeklyChecklist(input: {
    members: MemberCompleteness[];
    weeklyNote: WeeklyTeamNote | null;
    evidences: TeamRecapEvidence[];
    lock: RecapLockState | null;
}): ChecklistStep[] {
    const active = input.members.filter(m => m.status !== 'no_activity');
    const complete = active.filter(m => m.status === 'complete').length;
    const note = input.weeklyNote;
    const hasNote = !!(note && [note.uraian, note.obstacle, note.solution, note.follow_up_plan].some(v => v && v.trim()));
    const types = new Set(input.evidences.map(e => e.type));
    const missing = (['notula', 'photo', 'attendance'] as const).filter(t => !types.has(t));
    const names: Record<string, string> = { notula: 'Notula', photo: 'Dokumentasi', attendance: 'Daftar Hadir' };

    return [
        {
            label: 'Anggota lengkap',
            done: active.length > 0 && complete === active.length,
            detail: active.length ? `${complete} dari ${active.length} anggota lengkap.` : 'Belum ada kegiatan kipApp minggu ini.',
            target: 'kelengkapan-anggota',
        },
        {
            label: 'Ringkasan PJ',
            done: hasNote,
            detail: hasNote ? 'Ringkasan mingguan sudah diisi.' : 'Isi uraian, kendala, solusi dan RTL.',
            target: 'ringkasan-pj',
        },
        {
            label: 'Bukti rapat',
            done: missing.length === 0,
            detail: missing.length ? `Belum ada: ${missing.map(t => names[t]).join(', ')}.` : 'Notula, dokumentasi dan daftar hadir lengkap.',
            target: 'bukti-rapat',
        },
        lockStep(input.lock),
    ];
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
