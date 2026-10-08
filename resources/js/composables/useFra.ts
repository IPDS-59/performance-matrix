import * as XLSX from 'xlsx';
import type { FraIndicatorRow, FraSummary } from '@/types';

type Cell = string | number | null;

const ROMAN = ['I', 'II', 'III', 'IV'];

/** Number in the Indonesian style, up to two decimals; "-" when there is no value. */
export function fmt(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return value.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

/** Rows of the LK_Prov sheet (sheet "Kertas Kerja FRA"), with the values of one quarter in the analysis columns. */
export function buildFraSheet(indicators: FraIndicatorRow[], summary: FraSummary, year: number): Cell[][] {
    const q = summary.quarter;
    const rows: Cell[][] = [
        [`PENGUKURAN KINERJA TRIWULANAN TAHUN ${year}`],
        [],
        ['Satuan Kerja :', null, null, null, 'BPS Provinsi Sulawesi Tengah'],
        [`Nilai SAKIP ${year}:`, null, null, null, summary.sakip_score],
        [`Predikat SAKIP ${year}:`, null, null, null, summary.sakip_predicate],
        [],
        [
            'Kode', 'Tujuan/Sasaran/Indikator Kinerja', 'Jenis (IKU atau Proksi)', 'Jenis (Triwulanan atau Tahunan)', 'Jenis (% atau Non %)', 'Target', 'Satuan',
            ...ROMAN.map(r => `Alokasi Target TW ${r}`),
            ...ROMAN.map(r => `Realisasi TW ${r}`),
            ...ROMAN.map(r => `Capaian terhadap Target Triwulanan TW ${r}`),
            ...ROMAN.map(r => `Capaian terhadap Target Setahun TW ${r}`),
            'Kendala yang dihadapi pada Triwulan Berjalan', 'Solusi yang sudah dilakukan pada Triwulan Berjalan', 'Rencana Tindak lanjut',
            'PIC Tindak Lanjut', 'Batas Waktu Tindak lanjut', 'Link Bukti Dukung Kinerja', 'Link Bukti Dukung Tindak Lanjut Triwulan Sebelumnya',
            'Normalisasi Capaian PK (1)', 'Koreksi Normalisasi Capaian PK Berdasarkan Predikat SAKIP (2)', 'Nilai Akhir Capaian PK = (1)x(100%-(2))',
        ],
    ];

    let lastTujuan: string | null = null;
    let lastSasaran: string | null = null;

    for (const ind of indicators) {
        if (ind.tujuan && ind.tujuan !== lastTujuan) {
            rows.push([ind.tujuan]);
            lastTujuan = ind.tujuan;
        }
        if (ind.sasaran_code !== lastSasaran) {
            rows.push([ind.sasaran_code, ind.sasaran_name]);
            lastSasaran = ind.sasaran_code;
        }

        const v = ind.values[q];
        const quarters = [1, 2, 3, 4].map(n => ind.quarters[n]);
        rows.push([
            ind.code, ind.name, ind.kind, ind.period_type, ind.unit_type === 'percent' || ind.percent_label ? '%' : 'Non %', ind.target_value, ind.unit,
            ...quarters.map(x => x.allocation_value),
            ...quarters.map(x => x.realization_value),
            ...quarters.map(x => x.capaian_quarter ?? '-'),
            ...quarters.map(x => x.capaian_year ?? '-'),
            v?.obstacle ?? null, v?.solution ?? null, v?.follow_up ?? null, v?.pic ?? null, v?.deadline ?? null, v?.evidence_url ?? null, v?.previous_follow_up_url ?? null,
            ind.normalized, `${Math.round(ind.correction * 100)}%`, ind.final,
        ]);

        if (ind.unit_type === 'percent') {
            const pick = (key: 'allocation' | 'realization', axis: 'x' | 'y') =>
                [1, 2, 3, 4].map(n => ind.values[n]?.[`${key}_${axis}` as 'allocation_x'] ?? null);
            rows.push([null, `X: ${ind.x_label ?? ''}`, null, null, null, ind.target_x, null, ...pick('allocation', 'x'), ...pick('realization', 'x')]);
            rows.push([null, `Y: ${ind.y_label ?? ''}`, null, null, null, ind.target_y, null, ...pick('allocation', 'y'), ...pick('realization', 'y')]);
        }
    }

    rows.push([]);
    rows.push([null, `Capaian Kinerja IKU TW ${ROMAN[q - 1]}`, null, null, null, null, null, ...Array(12).fill(null), summary.iku_capaian_quarter ?? '-', null, null, null, summary.iku_capaian_year ?? '-']);
    for (const s of summary.sasaran) rows.push([null, `Capaian Kinerja Sasaran ${s.code}`, null, null, null, null, null, ...Array(15).fill(null), s.capaian_year ?? '-']);
    rows.push([]);
    rows.push([null, 'NKO Rata-rata Capaian PK', null, null, null, null, null, ...Array(24).fill(null), summary.nko ?? '-']);
    rows.push([null, 'Predikat PKO', null, null, null, null, null, ...Array(24).fill(null), summary.pko_predicate ?? '-']);

    return rows;
}

export function downloadFraSheet(indicators: FraIndicatorRow[], summary: FraSummary, year: number) {
    const sheet = XLSX.utils.aoa_to_sheet(buildFraSheet(indicators, summary, year));
    const book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, sheet, 'LK_Prov');
    XLSX.writeFile(book, `Kertas-Kerja-TW${summary.quarter}-${year}.xlsx`);
}
