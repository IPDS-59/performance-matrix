import { describe, it, expect } from 'vitest';
import { buildRecapSheet } from '@/composables/useRecapExport';
import type { RecapExport, RecapRow } from '@/types';

function row(overrides: Partial<RecapRow> = {}): RecapRow {
    return {
        row_key: '1:1', performance_plan_id: 1, project_id: 1, rk_description: 'RK', uraian_aggregated: 'uraian anggota',
        target: 4, realization: 2, achievement: 50, obstacle: 'kendala', solution: null, follow_up_plan: null,
        obstacle_aggregated: null, solution_aggregated: null, follow_up_aggregated: null, is_overridden: false, contributors: [],
        ...overrides,
    };
}

describe('buildRecapSheet', () => {
    it('groups rows per team and Projek with merged cells, like Rapat Mingguan', () => {
        const data: RecapExport = {
            period_type: 'week',
            week_start: '2026-06-01',
            teams: [
                {
                    team_name: 'MTI',
                    segments: [
                        { project_id: 1, project_name: 'Projek A', rows: [row(), row({ row_key: '2:1', pj_uraian: 'uraian PJ' })] },
                        { project_id: 2, project_name: 'Projek B', rows: [row({ row_key: '1:2' })] },
                    ],
                    evidences: { notula: ['https://n1', 'https://n2'] },
                },
                { team_name: 'Umum', segments: [], evidences: {} },
            ],
        };

        const { rows, merges } = buildRecapSheet(data);

        expect(rows[0]).toContain('Notula');
        expect(rows[1].slice(0, 5)).toEqual([1, 'MTI', 'Projek A', 'RK', 'uraian anggota']);
        expect(rows[2][4]).toBe('uraian PJ');
        expect(rows[3][2]).toBe('Projek B');
        expect(rows[1][11]).toBe('https://n1\nhttps://n2');
        expect(rows[4].slice(0, 5)).toEqual([2, 'Umum', null, null, 'Tidak ada kegiatan']);
        // Team "No"/"Tim" span rows 1-3; Projek A spans rows 1-2.
        expect(merges).toContainEqual({ s: { r: 1, c: 1 }, e: { r: 3, c: 1 } });
        expect(merges).toContainEqual({ s: { r: 1, c: 2 }, e: { r: 2, c: 2 } });
    });

    it('adds the FRA follow-up columns for a quarterly export', () => {
        const { rows } = buildRecapSheet({
            period_type: 'quarter',
            week_start: null,
            teams: [{
                team_name: 'MTI',
                segments: [{ project_id: null, project_name: 'MTI', rows: [row({ follow_up_pic: 'Dewi', follow_up_deadline: '2026-07-01' })] }],
                evidences: {},
            }],
        });

        expect(rows[0].slice(-3)).toEqual(['Link Bukti Tindak Lanjut', 'PIC', 'Batas Waktu']);
        expect(rows[1].slice(-2)).toEqual(['Dewi', '2026-07-01']);
    });
});
