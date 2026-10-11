export interface PlanFlowGuideStep {
    title: string;
    body: string;
    /** Where the member does this step. */
    where: 'kipApp' | 'Kinetik';
}

/**
 * The order of work in kipApp, from a walkthrough by a kipApp user on 2026-10-11.
 * Steps 1 to 3 happen in kipApp; Kinetik takes over from step 4.
 */
export const PLAN_FLOW_GUIDE: PlanFlowGuideStep[] = [
    {
        title: 'Siapkan RK Anggota',
        body: 'Awal tahun: PK, tujuan, sasaran, dan IKU diisi, lalu tim kerja, RK Ketua, projek, dan RK Anggota. RK Anggota harus ada sebelum SKP Tahunan dibuat.',
        where: 'kipApp',
    },
    {
        title: 'Buat SKP Tahunan dan minta persetujuan',
        body: 'Perencanaan Kinerja > Tahunan > Add. Periodenya 1 Januari sampai 31 Desember. Pegawai pindahan punya dua SKP dalam setahun. Pilih RK Anggota, kirim, lalu kepala menyetujui.',
        where: 'kipApp',
    },
    {
        title: 'Buat SKP Periodik tiap triwulan',
        body: 'Setelah SKP Tahunan disetujui: Perencanaan Kinerja > Periodik > Add, pilih triwulan dan RK yang masuk. Tanpa SKP triwulan, kegiatan belum bisa dicatat.',
        where: 'kipApp',
    },
    {
        title: 'Rencanakan pekerjaan minggu ini',
        body: 'Pilih RK, tulis apa yang akan dikerjakan, dan tanggalnya. Rencana dikirim ke kipApp otomatis pada tanggal mulainya, atau kirim sendiri sekarang.',
        where: 'Kinetik',
    },
    {
        title: 'Tandai selesai dengan capaian dan bukti dukung',
        body: 'Isi capaian dan link bukti dukung. Progres 100% dikirim ke kipApp atas nama Anda.',
        where: 'Kinetik',
    },
    {
        title: 'Kirim ke atasan',
        body: 'Kegiatan yang selesai dikirim agar atasan dapat menilai. Pengiriman bisa dibatalkan selama belum dinilai.',
        where: 'Kinetik',
    },
];
