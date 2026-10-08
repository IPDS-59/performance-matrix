import { describe, expect, it } from 'vitest';
import { buildFraSheet, fmt } from '@/composables/useFra';
import type { FraIndicatorRow, FraSummary } from '@/types';

const quarter = (over = {}) => ({ allocation_value: 0, realization_value: 0, capaian_quarter: null, capaian_year: null, ...over });
const indicator = (over: Partial<FraIndicatorRow> = {}): FraIndicatorRow => ({
    id: 1, sort_order: 0, tujuan: 'T1: Tujuan', sasaran_code: '2.6.1', sasaran_name: 'Edukasi', code: '2.6.1.1', name: 'Persentase kegiatan',
    kind: 'IKU', period_type: 'Triwulanan', unit_type: 'percent', percent_label: false, unit: 'Persen', x_label: 'Terlaksana', y_label: 'Direncanakan',
    target_x: 32, target_y: 40, owner_team_id: null, target_value: 80,
    quarters: { 1: quarter(), 2: quarter({ allocation_value: 40, realization_value: 47.5, capaian_quarter: 118.75, capaian_year: 59.375 }), 3: quarter(), 4: quarter() },
    values: { 2: { allocation_x: 16, allocation_y: 40, realization_x: 19, realization_y: 40, obstacle: 'Kendala A', solution: null, follow_up: null, pic: 'Tim IPDS', deadline: null, evidence_url: null, previous_follow_up_url: null } },
    normalized: 59.375, correction: 0.1, final: 53.4375,
    ...over,
});
const summary: FraSummary = { quarter: 2, sakip_score: 78.1, sakip_predicate: 'BB/Sangat Baik', iku_capaian_quarter: 118.75, iku_capaian_year: 59.375, sasaran: [{ code: '2.6.1', name: 'Edukasi', capaian_year: 59.375 }], nko: 53.4375, pko_predicate: 'BURUK' };

describe('useFra', () => {
    it('formats numbers the Indonesian way and "-" for none', () => {
        expect(fmt(1234.5)).toBe('1.234,5');
        expect(fmt(null)).toBe('-');
    });

    it('lays the sheet out like LK_Prov: headers, indicator row with X and Y rows, then totals', () => {
        const rows = buildFraSheet([indicator()], summary, 2026);

        expect(rows[0][0]).toBe('PENGUKURAN KINERJA TRIWULANAN TAHUN 2026');
        expect(rows[3][4]).toBe(78.1);
        expect(rows[7]).toEqual(['T1: Tujuan']);
        expect(rows[8]).toEqual(['2.6.1', 'Edukasi']);
        const row = rows[9];
        expect(row.slice(0, 7)).toEqual(['2.6.1.1', 'Persentase kegiatan', 'IKU', 'Triwulanan', '%', 80, 'Persen']);
        expect(row[8]).toBe(40);        // alokasi TW II
        expect(row[12]).toBe(47.5);     // realisasi TW II
        expect(row[16]).toBe(118.75);   // capaian triwulan TW II
        expect(row[17]).toBe('-');      // capaian triwulan TW III: no result yet
        expect(row[23]).toBe('Kendala A');
        expect(row[31]).toBe('10%');
        expect(rows[10][1]).toBe('X: Terlaksana');
        expect(rows[10][5]).toBe(32);
        expect(rows[11][1]).toBe('Y: Direncanakan');
        expect(rows[rows.length - 1][1]).toBe('Predikat PKO');
    });
});
