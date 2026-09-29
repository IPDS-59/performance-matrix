import { ref } from 'vue';
import * as XLSX from 'xlsx';
import type { RecapExport, RecapPeriodType, RecapRow } from '@/types';

type Cell = string | number | null;

export interface RecapSheet {
    rows: Cell[][];
    merges: XLSX.Range[];
}

const BASE_HEADER = [
    'No', 'Tim PJK', 'Projek', 'Rencana Kinerja', 'Uraian', 'Target', 'Realisasi',
    'Capaian Kinerja (%)', 'Permasalahan', 'Solusi', 'Rencana Tindak Lanjut',
];
const WEEK_HEADER = ['Notula', 'Dokumentasi', 'Daftar Hadir'];
const FRA_HEADER = ['Link Bukti Tindak Lanjut', 'PIC', 'Batas Waktu'];

function uraianOf(row: RecapRow): string | null {
    return row.pj_uraian || row.uraian_aggregated || null;
}

/**
 * Lay out the recap like the old Rapat Mingguan / Rapat Bulanan sheets:
 * one block per team, rows grouped by Projek, team and Projek cells merged.
 */
export function buildRecapSheet(data: RecapExport): RecapSheet {
    const type = data.period_type;
    const header = [
        ...BASE_HEADER,
        ...(type === 'week' ? WEEK_HEADER : []),
        ...(type === 'quarter' ? FRA_HEADER : []),
    ];
    const rows: Cell[][] = [header];
    const merges: XLSX.Range[] = [];
    const mergeDown = (col: number, from: number, to: number) => {
        if (to > from) merges.push({ s: { r: from, c: col }, e: { r: to, c: col } });
    };

    data.teams.forEach((team, index) => {
        const teamStart = rows.length;
        const evidence = type === 'week'
            ? [team.evidences.notula, team.evidences.photo, team.evidences.attendance].map((urls) => urls?.join('\n') ?? null)
            : [];

        const segments = team.segments.filter((seg) => seg.rows.length);
        if (!segments.length) {
            rows.push([index + 1, team.team_name, null, null, 'Tidak ada kegiatan', ...Array(header.length - 5).fill(null)]);
            evidence.forEach((value, i) => { rows[teamStart][BASE_HEADER.length + i] = value; });
            return;
        }

        segments.forEach((seg) => {
            const projectStart = rows.length;
            seg.rows.forEach((row) => {
                const line: Cell[] = [
                    null, null, null,
                    row.rk_description,
                    uraianOf(row),
                    row.target,
                    row.realization,
                    row.achievement,
                    row.obstacle,
                    row.solution,
                    row.follow_up_plan,
                ];
                if (type === 'week') line.push(null, null, null);
                if (type === 'quarter') line.push(row.follow_up_evidence_url ?? null, row.follow_up_pic ?? null, row.follow_up_deadline ?? null);
                rows.push(line);
            });
            rows[projectStart][2] = seg.project_name;
            mergeDown(2, projectStart, rows.length - 1);
        });

        rows[teamStart][0] = index + 1;
        rows[teamStart][1] = team.team_name;
        evidence.forEach((value, i) => { rows[teamStart][BASE_HEADER.length + i] = value; });
        const teamEnd = rows.length - 1;
        [0, 1, ...evidence.map((_, i) => BASE_HEADER.length + i)].forEach((col) => mergeDown(col, teamStart, teamEnd));
    });

    return { rows, merges };
}

/**
 * Download the office-wide recap for a period as .xlsx.
 */
export function useRecapExport() {
    const exporting = ref(false);
    const error = ref<string | null>(null);

    async function download(params: { period_type: RecapPeriodType } & Record<string, string | number>, filename: string) {
        exporting.value = true;
        error.value = null;
        try {
            const query = new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)]));
            const response = await fetch(`${route('team-recap.export')}?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Gagal mengambil data rekap.');

            const { rows, merges } = buildRecapSheet(await response.json() as RecapExport);
            const sheet = XLSX.utils.aoa_to_sheet(rows);
            sheet['!merges'] = merges;
            sheet['!cols'] = rows[0].map((_, i) => ({ wch: [4, 22, 28, 36, 48][i] ?? 24 }));

            const book = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(book, sheet, 'Rekap');
            XLSX.writeFile(book, filename);
        } catch (e: unknown) {
            error.value = e instanceof Error ? e.message : 'Gagal mengunduh rekap.';
        } finally {
            exporting.value = false;
        }
    }

    return { exporting, error, download };
}
