/**
 * Page tours, keyed by route name. A tour opens by itself the first time a user visits the page and again from the
 * "Panduan halaman" button. Steps point at elements by CSS selector; a step whose element is not on the screen
 * (another view of the same page, a feature that is switched off) is skipped, so one tour can cover every view.
 */
export interface TourStep {
    element: string;
    title: string;
    description: string;
}

export interface Tour {
    steps: TourStep[];
}

export const TOURS: Record<string, Tour> = {
    'weekly.index': {
        steps: [
            { element: 'div:has(> [aria-label="Tahun sebelumnya"])', title: 'Pilih tahun', description: 'Daftar di bawahnya menunjukkan tiap bulan: jumlah kegiatan, yang sudah ada capaiannya, dan yang sudah dikirim ke atasan.' },
            { element: 'main table, [data-tour="period-table"]', title: 'Bulan, lalu minggu', description: 'Klik satu bulan untuk melihat minggunya, lalu klik satu minggu untuk membuka kegiatannya.' },
            { element: '[aria-label="Cari kegiatan"]', title: 'Cari kegiatan', description: 'Ketik sebagian uraian kegiatan atau nama RK untuk menyaring daftar.' },
            { element: '[data-tour="sync-me"]', title: 'Sinkronkan data saya', description: 'Menarik RK dan kegiatan terbaru dari kipApp. Berguna bila daftar RK Anda kosong atau baru berubah.' },
            { element: '[data-tour="add-item"]', title: 'Tambah kegiatan atau rencana', description: 'Tulis rencana minggu ini paling lambat Senin pukul 09.00. Kegiatan yang sudah terjadi juga dicatat dari sini, cukup sekali.' },
            { element: '#h-last', title: 'Capaian minggu lalu', description: 'Isi target dan capaian berupa angka, uraian, dan link bukti, lalu tandai selesai. Dari sini kegiatan bisa dikirim ke atasan.' },
            { element: '#h-now', title: 'Rencana minggu ini', description: 'Rencana yang tanggalnya tiba dikirim ke kipApp otomatis. Klik satu baris untuk melihat detail, mengubah, atau menyalin.' },
        ],
    },
    'skp.index': {
        steps: [
            { element: '[aria-labelledby="tahunan-title"]', title: 'SKP Tahunan', description: 'Statusnya dibaca langsung dari kipApp: Sedang dibuat, menunggu atasan, lalu Ditetapkan. Triwulan baru bisa dibuat setelah SKP Tahunan Ditetapkan.' },
            { element: 'article[aria-label^="SKP TW"]', title: 'SKP per triwulan', description: 'Satu kartu tiap triwulan. Dari sini Anda membuat SKP triwulan, menambah RK, dan mengirimnya untuk dinilai atasan.' },
            { element: 'article[aria-label^="SKP TW"] details', title: 'RK dan IKI', description: 'SKP tidak bisa dikirim bila ada RK tanpa IKI. Bila fitur IKI menyala, tombol Tambah IKI ada di sini.' },
            { element: 'article[aria-label^="SKP TW"] button', title: 'Tombol yang tersedia', description: 'Tombolnya mengikuti status SKP. Bila SKP sudah dikirim, muncul Batalkan pengiriman selama atasan belum menilainya.' },
        ],
    },
    'skp.approvals': {
        steps: [
            { element: 'section[aria-labelledby="menunggu-title"]', title: 'SKP yang menunggu Anda', description: 'Hanya SKP yang atasannya Anda dan sedang menunggu. Anda mendapat notifikasi saat pegawai mengirim SKP.' },
            { element: 'td button[aria-expanded]', title: 'Bahan penilaian', description: 'Klik nama pegawai pada SKP triwulan untuk melihat kegiatan, capaian dibanding target, dan catatan tiap RK.' },
            { element: 'td a[href*="nilai"], td a[href*="kipapp"]', title: 'Menilai', description: 'Membuka halaman Nilai SKP. Isi nilai dan umpan balik tiap RK dan perilaku, lalu Selesai menilai.' },
            { element: 'td button.border', title: 'Tetapkan atau Kembalikan', description: 'Tetapkan untuk SKP Tahunan, Kembalikan untuk mengirim SKP kembali ke pegawai. Kinetik bertanya dulu dan memakai akun kipApp Anda sendiri.' },
        ],
    },
    'credit.mine': {
        steps: [
            { element: '[aria-labelledby="progres-title"]', title: 'Progres angka kredit', description: 'Estimasi dari predikat SKP tiap bulan, dibanding angka kredit yang dibutuhkan untuk naik. Bukan PAK resmi.' },
            { element: '[aria-labelledby="pak-title"]', title: 'AK dari PAK terakhir', description: 'Titik awal hitungan. Isi angka dan tanggal PAK terakhir Anda supaya estimasi mulai dari angka yang benar.' },
            { element: '[aria-labelledby="riwayat-title"]', title: 'Riwayat per triwulan', description: 'Tombol Ubah mengganti angka satu triwulan dengan alasan wajib. Triwulan itu bertanda "Diubah manual" dan PJ serta pimpinan bisa melihatnya.' },
        ],
    },
    'team-recap.weekly': {
        steps: [
            { element: '[aria-label="Pilih tim"]', title: 'Pilih tim', description: 'Rekap menampilkan satu tim. PJ memilih tim yang dipimpinnya.' },
            { element: 'main table', title: 'Bulan, lalu minggu', description: 'Kolom RK sudah ditulis dan Terkunci menunjukkan sejauh mana rekap sudah dikerjakan. Klik satu baris untuk membukanya.' },
            { element: '#bukti-rapat', title: 'Bukti dukung rapat', description: 'PJ menambahkan link notulen, daftar hadir, atau foto rapat sebagai bukti.' },
        ],
    },
    'projects.create': {
        steps: [
            { element: '#name', title: 'Nama proyek', description: 'Proyek mengelompokkan RK Anggota satu tim. Tulis nama seperti di kipApp.' },
            { element: '[aria-label="RK Ketua di kipApp"]', title: 'RK Ketua di kipApp', description: 'Pilih RK Ketua tempat proyek digantung di kipApp. Belum ada? Tekan RK Ketua baru. Kosongkan bila proyek hanya perlu ada di Kinetik.' },
            { element: 'button[type="submit"]', title: 'Simpan', description: 'Proyek disimpan di Kinetik. Bila RK Ketua dipilih, proyek juga dibuat di kipApp dengan akun Anda. Bila kipApp menolak, data tetap tersimpan dan bisa dikirim ulang dari daftar proyek.' },
        ],
    },
    'admin.features': {
        steps: [
            { element: 'ul.divide-y', title: 'Saklar fitur kipApp', description: 'Nyalakan atau matikan fitur yang bekerja dengan kipApp tanpa menyentuh server. Berlaku segera untuk semua pengguna.' },
            { element: 'ul.divide-y li:first-child button', title: 'Nyalakan atau matikan', description: 'Fitur bertanda "Belum diuji langsung" menulis data resmi di kipApp, jadi Kinetik bertanya dulu sebelum menyalakannya.' },
        ],
    },
};
