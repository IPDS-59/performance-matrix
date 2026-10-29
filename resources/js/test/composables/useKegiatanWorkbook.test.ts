import { describe, expect, it } from 'vitest';
import { parseKegiatanRows } from '@/composables/useKegiatanWorkbook';

const header = [null, 'No', 'Nama Anggota', 'Projek', 'Tanggal', 'Uraian', null, 'Target', 'Realisasi', 'Capaian Kinerja (%)', 'Permasalahan'];

describe('parseKegiatanRows', () => {
    it('reads rows by header label and carries the member and projek down', () => {
        const rows = parseKegiatanRows({
            'Neraca Wilayah': [
                [], [], header,
                [null, -1, -2, -3, -4, -5, null, -6, -7, -8, -9],
                [null, 1, 'Aditya Saputra, SST', 'Publikasi PDRB', null, 'Penyiapan kuesioner', null, 4, 3, 75, null],
                [null, null, null, null, new Date(2026, 5, 12), 'Rapat koordinasi tim', null, '2', '2', 100, null],
                [null, 2, 'Ananto Yanuar, SST', null, null, null, null, null, null, null, null],
            ],
            Proyek: [[null, 'No', 'Tim PJK', 'Projek']],
        });

        expect(rows).toHaveLength(2);
        expect(rows[0]).toMatchObject({ sheet: 'Neraca Wilayah', member: 'Aditya Saputra, SST', project: 'Publikasi PDRB', description: 'Penyiapan kuesioner', target: 4, realization: 3, percent: 75, date_start: null });
        expect(rows[1]).toMatchObject({ member: 'Aditya Saputra, SST', project: 'Publikasi PDRB', date_start: '2026-06-12', date_end: '2026-06-12', target: 2 });
    });

    it('handles the layout with separate date and time columns', () => {
        const rows = parseKegiatanRows({
            Umum: [
                [null, 'No', 'Nama Anggota', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Projek', 'Uraian Pekerjaan', null, 'Target', 'Realisasi', 'Capaian Kinerja (%)', 'Capaian', 'Permasalahan', null, 'Solusi', null, 'Rencana Tindak lanjut', null, 'Link Data Dukung'],
                [null, 1, 'Asmawati, S.Si', new Date(2026, 5, 3), new Date(1899, 11, 30, 8, 0), new Date(1899, 11, 30, 16, 0), 'Pengembangan SDM', 'Menyusun bahan pelatihan', null, 1, 1, 100, 'Selesai disusun', null, null, null, null, null, null, 'https://bukti.test/a'],
            ],
        });

        expect(rows[0]).toMatchObject({ date_start: '2026-06-03', time_start: '08:00', time_end: '16:00', project: 'Pengembangan SDM', note: 'Selesai disusun', link: 'https://bukti.test/a' });
    });

    it('reads the text under a merged Uraian header, repeated headers and typed date ranges', () => {
        const rows = parseKegiatanRows({
            'Humas dan Protokoler': [
                [null, 'No', 'Nama Anggota', 'Projek', 'Tanggal Mulai', 'Tanggal Selesai', 'Uraian', null, 'Target', 'Realisasi', 'Capaian Kinerja (%)'],
                [null, 2, 'Ananto Yanuar, SST', 'Laporan Protokoler', null, null, 1, 'Aktivitas Protokoler kunjungan tamu', 1, 1, 100],
                [null, null, null, null, null, null, 2, 'Dokumentasi liputan kegiatan', 1, 1, 100],
                [null, 'No', 'Nama Anggota', 'Projek', 'Tanggal', 'Uraian Pekerjaan', null, 'Target'],
                [null, 1, 'Bayu Setyawan, SST', 'Metodologi', '2-3 Juni', 'Rapat koordinasi metodologi', null, 3],
            ],
        }, 2026);

        expect(rows.map((r) => r.description)).toEqual(['Aktivitas Protokoler kunjungan tamu', 'Dokumentasi liputan kegiatan', 'Rapat koordinasi metodologi']);
        expect(rows[2]).toMatchObject({ member: 'Bayu Setyawan, SST', project: 'Metodologi', date_start: '2026-06-02', date_end: '2026-06-03', target: 3 });
    });
});
