/**
 * Content of the in-app user guide (Panduan). One flow per role, written as
 * numbered steps that point at the real menu and page. Keep in sync with the
 * sidebar labels in AppLayout.vue.
 */

export type GuideRole = 'anggota' | 'pj' | 'pimpinan' | 'admin';

export interface GuideMarker {
    n: number;
    /** Position in % of the screenshot, taken from the real element at capture time. */
    x: number;
    y: number;
    label: string;
}

export interface GuideShot {
    src: string;
    alt: string;
    width: number;
    height: number;
    markers: GuideMarker[];
}

// Screenshots of the real pages with mock names (public/images/pedoman).
export const SHOTS = {
    pjAngkaKreditTim: {
        src: '/images/pedoman/pj-angka-kredit-tim.webp',
        alt: 'Halaman Angka Kredit Tim untuk PJ, anggota diurutkan dari yang paling dekat ke kenaikan',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 37.6, y: 7.1, label: 'Saring anggota per status kenaikan.' },
            { n: 2, x: 45.2, y: 21.4, label: 'AK terkumpul dibanding AK yang dibutuhkan.' },
            { n: 3, x: 72.3, y: 21.4, label: 'Syarat minimal 2 tahun dalam golongan.' },
            { n: 4, x: 87.4, y: 21.4, label: 'Anggota paling dekat ke kenaikan tampil paling atas.' },
        ],
    },
    skpSaya: {
        src: '/images/pedoman/skp-saya.webp',
        alt: 'Halaman SKP Saya: SKP Tahunan dengan tiga tahapnya dan SKP tiap triwulan',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 29.4, y: 19.8, label: 'Tahap SKP Tahunan. Atasan menetapkan di kipApp.' },
            { n: 2, x: 93.8, y: 70.1, label: 'Status SKP tiap triwulan, sama seperti di kipApp.' },
            { n: 3, x: 29.5, y: 88.0, label: 'Triwulan yang belum punya SKP: tombol Buat SKP.' },
        ],
    },
    skpBuatTriwulan: {
        src: '/images/pedoman/skp-buat-triwulan.webp',
        alt: 'Popup Buat SKP TW 4 dengan daftar RK dari SKP Tahunan',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 27.4, y: 43.4, label: 'Centang RK SKP Tahunan yang masuk ke triwulan ini.' },
            { n: 2, x: 68.2, y: 64.0, label: 'Buat SKP. Di kipApp SKP-nya langsung ada.' },
        ],
    },
    panduanHalaman: {
        src: '/images/pedoman/panduan-halaman.webp',
        alt: 'Halaman Rekap Mingguan dengan panduan yang menyorot pilihan tahun dan kotak penjelasan Lanjut',
        width: 1024, height: 700,
        markers: [
            { n: 1, x: 86.7, y: 4.4, label: 'Panduan halaman: membuka lagi panduan halaman ini kapan saja.' },
            { n: 2, x: 42, y: 35.7, label: 'Bagian yang disorot dijelaskan di kotak ini.' },
            { n: 3, x: 53.2, y: 45.3, label: 'Lanjut ke bagian berikutnya. Esc atau tanda silang menutup panduan.' },
        ],
    },
    skpIki: {
        src: '/images/pedoman/skp-iki.webp',
        alt: 'SKP TW 4 yang Sedang dibuat dengan daftar RK dan IKI, tombol Tambah IKI dan kotak untuk menulis IKI baru',
        width: 1024, height: 760,
        markers: [
            { n: 1, x: 34.9, y: 36.6, label: 'Tambah IKI pada RK yang belum punya.' },
            { n: 2, x: 62.5, y: 67.4, label: 'Tulis IKI beserta targetnya, minimal 10 huruf.' },
            { n: 3, x: 34.7, y: 77.9, label: 'Simpan IKI: dibuat di kipApp dengan akun Anda.' },
            { n: 4, x: 45.9, y: 85.8, label: 'Setelah tiap RK punya IKI, SKP bisa dikirim.' },
        ],
    },
    skpKirim: {
        src: '/images/pedoman/skp-kirim.webp',
        alt: 'SKP TW 4 berstatus Sedang dibuat dengan tombol Tambah RK dan Kirim untuk dinilai',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 33.7, y: 93.6, label: 'Tambah RK selama SKP masih Sedang dibuat.' },
            { n: 2, x: 45.9, y: 93.6, label: 'Kirim untuk dinilai atasan. Setelah itu tombolnya menjadi Batalkan pengiriman.' },
        ],
    },
    adminFitur: {
        src: '/images/pedoman/admin-fitur.webp',
        alt: 'Halaman Fitur: daftar fitur kipApp dengan status Menyala atau Mati dan tombol Nyalakan atau Matikan',
        width: 1024, height: 620,
        markers: [
            { n: 1, x: 91, y: 28, label: 'Matikan atau nyalakan fitur. Berlaku segera untuk semua pengguna.' },
            { n: 2, x: 62, y: 87, label: 'Tanda "Belum diuji langsung": fitur yang menulis data resmi kipApp dan belum pernah dicoba di kipApp asli.' },
        ],
    },
    skpNilai: {
        src: '/images/pedoman/skp-nilai.webp',
        alt: 'Halaman Nilai SKP: bahan penilaian dari Kinetik, lalu nilai dan umpan balik untuk tiap RK dan perilaku',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 39, y: 27, label: 'Bahan penilaian dari Kinetik: kegiatan dan capaian tiap RK.' },
            { n: 2, x: 90.5, y: 60.7, label: 'Nilai 0 sampai 100 untuk tiap RK. Tingkatnya muncul sendiri di bawahnya.' },
            { n: 3, x: 90.5, y: 69.2, label: 'Simpan mengirim nilai baris itu ke kipApp.' },
        ],
    },
    proyekBaruKipapp: {
        src: '/images/pedoman/proyek-baru-kipapp.webp',
        alt: 'Form Tambah Proyek dengan pilihan RK Ketua di kipApp dan tombol RK Ketua baru',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 31, y: 20.5, label: 'Pilih tim lebih dulu, daftar RK Ketua dimuat dari kipApp.' },
            { n: 2, x: 40, y: 36.5, label: 'RK Ketua tempat proyek digantung di kipApp.' },
            { n: 3, x: 66, y: 36.5, label: 'Belum ada RK Ketua? Buat dari sini, pilih IKI JPT-nya.' },
        ],
    },
    skpPersetujuan: {
        src: '/images/pedoman/skp-persetujuan.webp',
        alt: 'Halaman Persetujuan SKP: SKP triwulan yang menunggu dinilai dengan rincian kegiatan dan capaian tiap RK, tombol Nilai dan Kembalikan',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 29.3, y: 23, label: 'Klik nama pegawai pada SKP triwulan untuk membuka bahan penilaian.' },
            { n: 2, x: 44, y: 34, label: 'Tiap RK dengan kegiatan, capaian dibanding target, dan catatan capaian.' },
            { n: 3, x: 84, y: 23.3, label: 'Nilai SKP triwulan. Untuk SKP Tahunan tombolnya Tetapkan.' },
            { n: 4, x: 91.8, y: 23.3, label: 'Kembalikan ke pegawai. SKP kembali ke Sedang dibuat.' },
        ],
    },
    skpPersetujuanPj: {
        src: '/images/pedoman/skp-persetujuan-pj.webp',
        alt: 'Halaman Persetujuan SKP untuk ketua tim: SKP anggota tim yang menunggu, dengan tombol Tetapkan dan Kembalikan',
        width: 1024, height: 860,
        markers: [
            { n: 1, x: 12.5, y: 63.1, label: 'Menu Persetujuan SKP muncul bila ada SKP anggota yang atasannya Anda.' },
            { n: 2, x: 34.5, y: 29.5, label: 'Satu baris per SKP anggota tim yang menunggu Anda.' },
            { n: 3, x: 82.4, y: 29.5, label: 'Tetapkan SKP Tahunan anggota. Kinetik bertanya dulu.' },
            { n: 4, x: 92, y: 29.5, label: 'Kembalikan ke anggota untuk diperbaiki.' },
            { n: 5, x: 79.4, y: 23.3, label: 'SKP triwulan dinilai di kipApp.' },
        ],
    },
    anggotaBeranda: {
        src: '/images/pedoman/anggota-beranda.webp',
        alt: 'Beranda anggota: dua kotak, yaitu rencana minggu ini dan capaian minggu lalu',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 60, y: 26, label: 'Rencana minggu ini. Merah atau kuning bila belum ada rencana.' },
            { n: 2, x: 96, y: 26, label: 'Berapa kegiatan minggu lalu yang capaiannya sudah diisi, dan mana yang belum.' },
        ],
    },
    anggotaAngkaKredit: {
        src: '/images/pedoman/anggota-angka-kredit.webp',
        alt: 'Halaman Angka Kredit Saya dengan kemajuan, AK dari PAK dan rumus per triwulan',
        width: 1024, height: 932,
        markers: [
            { n: 1, x: 9.5, y: 19.6, label: 'Kenaikan berikutnya: pangkat atau jenjang.' },
            { n: 2, x: 9.5, y: 27.7, label: 'AK terkumpul dibanding AK yang dibutuhkan.' },
            { n: 3, x: 38.8, y: 31.9, label: 'Perkiraan jumlah triwulan lagi dengan predikat Baik.' },
            { n: 4, x: 9.5, y: 41.5, label: 'Bulan tanpa predikat dihitung sebagai estimasi.' },
            { n: 5, x: 9.5, y: 52.9, label: 'AK dari PAK terakhir: isi sendiri, lalu dicek admin.' },
            { n: 6, x: 87.1, y: 53.8, label: 'Isi atau ubah AK dan tanggal PAK.' },
            { n: 7, x: 42.7, y: 75, label: 'Rumus AK setiap triwulan.' },
            { n: 8, x: 84.2, y: 79.2, label: 'Final atau Estimasi.' },
        ],
    },
    pimpinanBeranda: {
        src: '/images/pedoman/head-beranda.webp',
        alt: 'Beranda pimpinan: empat kotak, yaitu rencana minggu ini, kegiatan ke atasan, rapat mingguan, dan SKP menunggu',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 56, y: 16.5, label: 'Berapa anggota sudah membuat rencana minggu ini, dan siapa yang belum.' },
            { n: 2, x: 97, y: 16.5, label: 'Berapa anggota sudah mengirim semua kegiatannya ke atasan, dan siapa yang belum.' },
            { n: 3, x: 56, y: 41, label: 'Tim yang belum punya rekap rapat mingguan untuk minggu lalu.' },
            { n: 4, x: 97, y: 41, label: 'SKP yang menunggu persetujuan Anda.' },
        ],
    },
    pimpinanMonitoring: {
        src: '/images/pedoman/head-monitoring.webp',
        alt: 'Halaman Monitoring: capaian per tim, peringkat tim, konsistensi perencanaan dan capaian pegawai',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 40, y: 25, label: 'Capaian tiap tim pada bulan yang dipilih.' },
            { n: 2, x: 30, y: 71, label: 'Konsistensi perencanaan: berapa dari 8 minggu terakhir pegawai membuat rencana. Tertinggi atau Terendah.' },
            { n: 3, x: 80, y: 71, label: 'Pegawai dengan capaian terbesar.' },
        ],
    },
    fraBulanan: {
        src: '/images/pedoman/fra-bulanan.webp',
        alt: 'Kertas Kerja FRA bulanan: tabel Januari sampai Desember dengan baris X, baris Y, dan persen berwarna',
        width: 1024, height: 358,
        markers: [
            { n: 1, x: 30, y: 20, label: 'Baris X dan Y per bulan. PJ tim pemilik mengisinya langsung di sel.' },
            { n: 2, x: 40, y: 62, label: 'Persen bulan itu, dihitung otomatis. Hijau: target tercapai. Kuning: belum. Merah: kosong atau nol pada bulan yang sudah lewat.' },
            { n: 3, x: 80, y: 80, label: 'Kumulatif dari Januari terhadap target tahunan. Bingkai menandai bulan yang dipilih.' },
        ],
    },
    imporKegiatan: {
        src: '/images/pedoman/impor-kegiatan.webp',
        alt: 'Halaman Impor Kegiatan dari Excel: pilihan minggu dan file, ringkasan baris, dan tabel pratinjau dengan pilihan RK',
        width: 1024, height: 533,
        markers: [
            { n: 1, x: 14, y: 20, label: 'Minggu untuk baris yang tidak punya tanggal. Baris itu menjadi rencana Senin sampai Jumat minggu ini.' },
            { n: 2, x: 52, y: 20, label: 'Pilih file Kegiatan Mingguan Anggota (.xlsx), lalu tekan Baca file.' },
            { n: 3, x: 20, y: 38, label: 'Ringkasan: baris siap, perlu pilih RK, sudah ada, dan yang tidak bisa diimpor.' },
            { n: 4, x: 83, y: 53, label: 'Pilih RK bila Kinetik tidak bisa menentukannya sendiri.' },
            { n: 5, x: 95, y: 38, label: 'Impor kegiatan yang dicentang.' },
        ],
    },
    pimpinanReviewBersama: {
        src: '/images/pedoman/pimpinan-review-bersama.webp',
        alt: 'Halaman Review Bersama untuk pimpinan: ringkasan kantor per bulan dan tabel capaian per tim yang bisa diurutkan',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 27, y: 17.9, label: 'Pindah bulan dengan ‹ dan ›. Review Bersama untuk pimpinan selalu per bulan.' },
            { n: 2, x: 20, y: 29, label: 'Ringkasan kantor dalam satu deret angka.' },
            { n: 3, x: 38, y: 35.8, label: 'Saring satu tim, atau per status capaian.' },
            { n: 4, x: 49.2, y: 41.4, label: 'Klik Capaian untuk mengurutkan dari tertinggi atau terendah.' },
            { n: 5, x: 87.7, y: 46.6, label: 'Status capaian tim: Tercapai, Progres, Rendah, atau Belum ada data.' },
            { n: 6, x: 18.4, y: 45.8, label: 'Panah membuka daftar projek tim.' },
            { n: 7, x: 91, y: 17.9, label: 'Unduh Excel semua tim untuk rapat.' },
        ],
    },
    ringkasanRapat: {
        src: '/images/pedoman/ringkasan-rapat.webp',
        alt: 'Halaman Ringkasan Rapat: tabel tim dan minggu dengan status Lengkap, Sebagian, atau Belum, dan jumlah bukti rapat',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 24.8, y: 11.8, label: 'Pindah bulan dengan ‹ dan ›.' },
            { n: 2, x: 31.3, y: 21.1, label: 'Ringkasan: rekap mingguan lengkap, rekap bulanan lengkap, tim yang punya bukti rapat.' },
            { n: 3, x: 51.4, y: 33.2, label: 'Lengkap: semua RK yang punya kegiatan sudah ada parafrasenya. Angka kecil di sebelahnya jumlah bukti rapat.' },
            { n: 4, x: 71.7, y: 33.2, label: 'Sebagian: baru sebagian RK yang ada parafrasenya.' },
            { n: 5, x: 92.9, y: 33.2, label: 'Rapat bulanan tim itu.' },
        ],
    },
    kepatuhanRencana: {
        src: '/images/pedoman/kepatuhan-rencana.webp',
        alt: 'Halaman Kepatuhan Rencana: tiga angka ringkasan, daftar anggota yang belum membuat rencana, dan tombol untuk melihat tabel',
        width: 1024, height: 576,
        markers: [
            { n: 1, x: 30.6, y: 19.4, label: 'Tiga angka: rencana minggu ini, rencana triwulan, dan jumlah yang belum punya rencana.' },
            { n: 2, x: 25, y: 29.2, label: 'Nama anggota yang belum membuat rencana, per tim.' },
            { n: 3, x: 25.2, y: 57.8, label: 'Tombol untuk membuka tabel per tim dan minggu.' },
        ],
    },
    pimpinanAngkaKredit: {
        src: '/images/pedoman/pimpinan-angka-kredit.webp',
        alt: 'Halaman Angka Kredit Tim untuk pimpinan, semua pegawai kantor',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 5.2, y: 7.1, label: 'Pilih satu tim atau semua tim.' },
            { n: 2, x: 37.6, y: 7.1, label: 'Saring per status kenaikan.' },
            { n: 3, x: 26.7, y: 21.4, label: 'Kenaikan berikutnya: pangkat atau jenjang.' },
            { n: 4, x: 45.2, y: 21.4, label: 'AK terkumpul dibanding AK yang dibutuhkan.' },
            { n: 5, x: 65, y: 21.4, label: 'Sisa AK dan perkiraan jumlah triwulan.' },
            { n: 6, x: 86.8, y: 21.4, label: 'Status: siap diusulkan, hampir, atau berjalan.' },
        ],
    },
    adminIntegrasi: {
        src: '/images/pedoman/admin-integrasi.webp',
        alt: 'Halaman Integrasi kipApp dengan status token dan tiga tombol sinkronisasi',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 6, y: 7.3, label: 'Status token: masih berlaku atau kedaluwarsa.' },
            { n: 2, x: 6, y: 73.1, label: 'Tempel token x-auth baru dari kipApp.' },
            { n: 3, x: 19.5, y: 82.7, label: 'Simpan token.' },
            { n: 4, x: 38.5, y: 39.1, label: 'Sinkronisasi Struktur: tim, projek, anggota, RK. Jalankan lebih dulu.' },
            { n: 5, x: 38.5, y: 7.3, label: 'Sinkronisasi Kegiatan: kegiatan harian semua pegawai (anggota menyinkronkan dirinya sendiri lewat "Sinkronkan data saya").' },
            { n: 6, x: 38.5, y: 70.9, label: 'Sinkronisasi Angka Kredit: golongan dan predikat SKP.' },
            { n: 7, x: 83.5, y: 71.9, label: 'Tekan Sinkronkan dan biarkan halaman terbuka sampai selesai.' },
        ],
    },
    devtoolsXauth: {
        src: '/images/pedoman/devtools-xauth.svg',
        alt: 'Ilustrasi Chrome DevTools, tab Network, header x-auth pada request kipApp. Token pada gambar adalah contoh.',
        width: 1024, height: 620,
        markers: [
            { n: 1, x: 21.7, y: 23.5, label: 'Buka tab Network.' },
            { n: 2, x: 32.8, y: 28.9, label: 'Ketik v1 di kolom filter dan pilih Fetch/XHR.' },
            { n: 3, x: 28.5, y: 42.4, label: 'Klik request user (atau request v1 lain).' },
            { n: 4, x: 31.8, y: 33.5, label: 'Buka tab Headers.' },
            { n: 5, x: 33, y: 64.7, label: 'Cari x-auth di Request Headers.' },
            { n: 6, x: 67, y: 76.6, label: 'Klik kanan baris x-auth, pilih Copy value.' },
        ],
    },
} satisfies Record<string, GuideShot>;

export interface GuideStep {
    title: string;
    /** Annotated screenshot shown under this step. */
    shot?: GuideShot;
    /** Where to go, as the user sees it in the menu. */
    where?: string;
    /** Route name for the "Buka" link. */
    route?: string;
    body: string[];
    tip?: string;
}

export interface GuideFlow {
    role: GuideRole;
    label: string;
    summary: string;
    /** The old spreadsheet this flow replaces. */
    replaces?: string;
    steps: GuideStep[];
}

/** First step of every non-admin flow. */
const SSO_LOGIN_STEP: GuideStep = {
    title: 'Masuk dengan akun SSO BPS',
    where: 'Halaman Masuk, lalu "Hubungkan akun SSO BPS" pada login pertama',
    body: [
        'Masuk ke Kinetik dengan username SSO BPS Anda (misalnya "nama" atau "nama@bps.go.id") dan kata sandi SSO yang sama dengan kipApp.',
        'Login pertama: sebelum akun terhubung, Anda masih dapat masuk dengan kata sandi bawaan dari admin. Kinetik langsung meminta kata sandi SSO Anda satu kali. Setelah kata sandi benar, kata sandi bawaan tidak berlaku lagi.',
        'Kata sandi SSO disimpan terenkripsi dan tidak pernah ditampilkan kembali. Kinetik memakainya untuk mengambil data kipApp atas nama Anda.',
        'Kata sandi bawaan hanya berlaku sampai batas waktu yang ditetapkan admin. Sesudah itu masuk wajib memakai kata sandi SSO.',
        'Jika akun SSO Anda memakai kode OTP, Kinetik meminta kode 6 digit dari aplikasi autentikator Anda tepat setelah kata sandi. Halaman kode hanya muncul untuk akun yang memakainya.',
    ],
    tip: 'Jika Anda mengganti kata sandi SSO di BPS, masuk ke Kinetik dengan kata sandi yang baru. Kinetik memperbaruinya otomatis. Akun admin tetap memakai kata sandi Kinetik sendiri.',
};

const PAGE_TOUR_STEP: GuideStep = {
    title: 'Ikuti panduan di tiap halaman',
    where: 'Tombol "Panduan halaman" di pojok kanan atas',
    body: [
        'Halaman utama Kinetik punya panduan singkat yang menyorot bagian penting satu per satu dan menjelaskannya. Panduan terbuka sendiri saat Anda pertama kali membuka halaman itu, sekali saja.',
        'Ingin melihatnya lagi? Tekan "Panduan halaman" di pojok kanan atas. Tombol itu hanya ada di halaman yang punya panduan. Esc atau tanda silang menutupnya.',
        'Menu di kiri dikelompokkan. Tekan judul kelompok (Kegiatan, Rekap Tim, Data Master) untuk melipat atau membukanya. Pilihan Anda diingat, dan kelompok yang berisi halaman yang sedang dibuka terbuka sendiri.',
    ],
    shot: SHOTS.panduanHalaman,
    tip: 'Panduan ini menjelaskan layar yang sedang Anda lihat. Langkah kerja lengkap tiap peran ada di Buku Pedoman ini.',
};

export const GUIDE_OVERVIEW = [
    { who: 'Anggota', what: 'Merencanakan kegiatan dan mengisi capaian mingguan di Rekap Mingguan.' },
    { who: 'PJ / Ketua Tim', what: 'Meninjau rekap tim, mengisi solusi dan rencana tindak lanjut, menambah bukti rapat, lalu mengunci rekap sebelum rapat.' },
    { who: 'Pimpinan', what: 'Membaca rekap semua tim di Review Bersama dan mengunduh Excel untuk rapat.' },
];

export const GUIDE_FLOWS: GuideFlow[] = [
    {
        role: 'anggota',
        label: 'Anggota',
        summary: 'Setiap minggu Anda merencanakan kegiatan per Rencana Kinerja (RK), lalu mengisi capaian (angka dan uraian) dan tautan bukti.',
        replaces: 'Sheet "Kegiatan Mingguan Anggota"',
        steps: [
            SSO_LOGIN_STEP,
            PAGE_TOUR_STEP,
            {
                title: 'Baca Beranda',
                where: 'Menu Beranda',
                route: 'dashboard',
                body: [
                    'Beranda menjawab dua hal untuk minggu ini: apakah rencana sudah ada, dan apakah realisasi minggu lalu sudah diisi. Tombol di tiap kotak membuka minggunya. Bila Anda PJ, di bawahnya ada "Status anggota tim": satu baris per anggota, siapa yang belum punya rencana atau belum mengisi realisasi, dengan tombol "Buka tabel rapat". Kegiatan ke atasan dan SKP diurus di kipApp.',
                    'Capaian tim dan peringkat ada di menu Monitoring. Menu "Pantau Kegiatan" hanya untuk melihat kegiatan kipApp Anda, bukan untuk mengisi.',
                ],
                shot: SHOTS.anggotaBeranda,
            },
            {
                title: 'Buka Rekap Mingguan',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Halaman ini dibuka dengan daftar bulan. Tiap baris menunjukkan jumlah kegiatan, berapa yang capaiannya sudah terisi, dan berapa yang sudah terkirim ke atasan. Pilih satu bulan untuk melihat minggu-minggunya, lalu pilih satu minggu. Baris dengan label "Sekarang" adalah bulan atau minggu berjalan. Tombol "Semua bulan" dan "Minggu di …" membawa Anda kembali satu langkah.',
                    'Di dalam sebuah minggu ada dua tabel dan hanya kegiatan Anda sendiri: "Realisasi minggu lalu" dan "Rencana minggu ini". Tampilannya meniru kipApp: No, Tanggal, Rencana Kinerja, Kegiatan, Progress, Capaian, Bukti Dukung, Capaian SKP, Dikirim. Tombol "Aksi" di tiap baris berisi Isi realisasi, Ubah, Perpanjang ke minggu depan (kegiatan berjalan seminggu lebih lama, tetap satu kegiatan, tanpa disalin), Salin kegiatan, dan Hapus atau Batalkan. Kegiatan yang berjalan beberapa minggu tampil di tiap minggunya bertanda "Lanjutan", dan realisasinya dicatat per minggu.',
                    'Jika PJ mengisi fokus untuk Anda, fokus itu tampil di atas tabel.',
                    'Tekan "Sinkronkan data saya" untuk menarik RK dan kegiatan Anda dari kipApp. Sinkronisasi pertama berjalan otomatis setelah Anda menghubungkan akun SSO. Jika daftar RK kosong, tekan tombol ini.',
                    'Kinetik juga menyinkronkan data Anda sendiri tiap pagi, memakai sesi kipApp Anda. Sesi yang berakhir diperbarui otomatis dengan kata sandi SSO yang tersimpan. Jika tidak bisa (kata sandi ditolak, atau akun memakai OTP), Anda mendapat notifikasi satu kali dan sinkronisasi Anda menunggu. Begitu Anda masuk lagi ke Kinetik, sinkronisasi yang menunggu langsung berjalan.',
                ],
            },
            {
                title: 'Rapat minggu pertama: isi target',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Tekan "Tambah". Pilih projek lebih dulu, lalu RK: daftar RK hanya menampilkan RK projek itu (keduanya bisa dicari). Setelah RK dipilih, projek terisi sendiri bila Kinetik tahu pasti: kipApp mencatat projek tempat tiap RK Anda dibuat (dibaca lewat Sinkronisasi Struktur), dan bila catatan itu belum ada Kinetik menyimpulkannya dari RK Ketua serta projek tempat Anda menjadi anggota. Bila ada beberapa kemungkinan, kolom projek berisi pilihan itu dan Anda memilih satu. Setelah dipilih, projek terkunci. Tekan "Ganti RK" bila ingin memilih projek lain, lalu pilih RK dari projek yang baru. Tulis kegiatan (minimal 10 karakter). Progres, capaian, bukti dukung, dan uraian capaian tidak ada di form ini: semuanya diisi nanti saat memperbarui capaian.',
                    'Ada dua kotak centang yang berdiri sendiri: "Gunakan periode tanggal" (tanggal mulai sampai selesai) dan "Gunakan jam" (jam mulai dan selesai, per 15 menit). Keduanya boleh dipakai bersamaan. Tanggal harus berada dalam satu triwulan.',
                    'Tidak yakin menulis apa? Tekan "List Aktivitas" di samping kolom Kegiatan. Pilih satu baris presensi dari kipApp (tekan tombol + atau klik dua kali). Kegiatan dan tanggalnya terisi, sisanya tetap Anda isi sendiri.',
                    'Pilihan "Masukkan ke capaian SKP" baru muncul di form Realisasi bila progres belum 100, seperti di kipApp. Pada progres 100 kegiatan selalu dihitung. Kegiatan yang lanjut ke bulan depan sebaiknya tidak dicentang. Ada juga kolom "Permasalahan" untuk kendala yang akan dibahas di rapat.',
                    'Form tambah tidak punya kolom progres. Untuk tanggal yang sudah lewat progres langsung 100, seperti di kipApp. Untuk rencana ke depan progres mulai dari 0, lalu terhitung dari capaian saat Anda mengisi Realisasi.',
                    'Isi juga Target (angka) dan Satuan. Progres, capaian (angka), tautan bukti dukung, dan uraian diisi nanti di form Realisasi pada detail kegiatan. Target dan capaian selalu angka; teks penjelasan ditulis di uraian capaian.',
                    'Anda dapat mengubah atau membatalkan kegiatan selama belum dikirim ke kipApp. Untuk membuat kegiatan serupa, klik barisnya lalu tekan "Salin kegiatan": form terisi dari kegiatan itu.',
                ],
            },
            {
                title: 'Rapat berikutnya: tinjau capaian lalu rencanakan',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Pada rapat minggu kedua dan seterusnya, mulai dari tabel "Capaian minggu lalu". Klik baris, lalu isi form Realisasi: capaian (angka), progres, tautan bukti dukung, dan uraian. Progres dihitung otomatis dari capaian dibagi target, dan bisa Anda ubah. Persentase capaian terhadap target juga dihitung otomatis.',
                    'Lalu tambahkan kegiatan untuk "Rencana minggu ini".',
                ],
            },
            {
                title: 'Bulan sudah lewat: isi capaian sekaligus',
                where: 'Menu Kegiatan → Isi Capaian Bulanan',
                route: 'realisasi.monthly',
                body: [
                    'Dipakai bila capaian satu bulan belum diisi, misalnya bulan Oktober yang baru Anda isi di bulan November. Pilih bulan, lalu semua kegiatan Anda di bulan itu tampil dalam satu tabel.',
                    'Isi target, satuan, capaian, uraian, dan tautan bukti di setiap baris, lalu tekan "Simpan". Anda tidak perlu membuka kegiatan satu per satu. Baris yang kegiatannya sudah ada di kipApp ikut diperbarui di kipApp bila uraian, bukti dukung, atau progresnya berubah.',
                    'Untuk kegiatan yang tercatat di kipApp, Kinetik memperbarui kipApp dalam simpanan yang sama. Uraian capaian untuk kipApp minimal 10 karakter. Kegiatan yang belum bisa dikirim, misalnya karena SKP-nya terkunci, tetap tersimpan di Kinetik dan muncul sebagai peringatan dengan alasannya.',
                    'Dengan cara ini Anda mengisi sekali, tidak bolak-balik antara Kinetik dan kipApp.',
                ],
                tip: 'Isi uraian capaian dulu sebelum menyimpan. Baris tanpa uraian hanya tersimpan di Kinetik.',
            },
            {
                title: 'Kegiatan di luar RK tim (RK tambahan)',
                where: 'Menu Kegiatan → Rekap Mingguan → Tambah',
                route: 'weekly.index',
                body: [
                    'Kalau ada pekerjaan yang tidak termasuk RK tim, misalnya tugas dari pimpinan, buat RK tambahan sendiri. Di form kegiatan, tekan "Pekerjaan di luar RK tim? Tambah RK tambahan", tulis RK-nya (minimal 10 karakter), lalu tekan "Simpan RK tambahan". Projek tidak perlu dipilih.',
                    'RK tambahan hanya milik Anda dan muncul di daftar RK pada form berikutnya. RK kipApp yang berjenis tambahan juga ikut tersinkron dan ditandai sama.',
                    'Pembuatan RK tambahan di kipApp memakai panggilan yang sama dengan tombol "Tambah Rencana Kinerja" di kipApp, dan sudah diuji langsung. Admin tetap bisa mematikannya (saklar "Kirim RK tambahan ke kipApp" di menu Fitur, hanya admin). Selama mati, kegiatannya tersimpan di Kinetik dan tidak dikirim.',
                    'Saklar "Gabungkan kegiatan berulang saat dikirim" di menu Fitur (hanya admin, mati sejak awal, belum diuji langsung ke kipApp): kegiatan lengkap milik satu orang, satu RK, bertulisan sama dalam satu bulan, dikirim sebagai satu kegiatan dengan rentang tanggal diperpanjang serta target dan capaian dijumlahkan. Yang digabung dibatalkan dan menunjuk ke yang dipertahankan.',
                ],
            },
            {
                title: 'Kirim ke kipApp dan tandai selesai',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Klik baris kegiatan untuk melihat semua isinya dan langkah berikutnya.',
                    'Kegiatan baru dikirim ke kipApp atas nama Anda hanya bila sudah lengkap: progres 100, uraian capaian (minimal 10 karakter), bukti dukung, dan angka capaian bila ada target. Kegiatan yang belum lengkap tetap di Kinetik sebagai "Direncanakan", dan detailnya menyebut apa yang kurang. Begitu lengkap dan tanggal mulainya tiba, Kinetik mengirimnya otomatis setiap jam kerja mulai pukul 05.10 WITA, juga bila tanggalnya sudah lewat. Ingin langsung? Tekan "Kirim ke kipApp", atau centang beberapa kegiatan dan tekan "Kirim ke kipApp (n)".',
                    'Awal triwulan baru belum ada SKP triwulannya di kipApp. Kinetik membuatnya otomatis dari SKP Tahunan Anda, seperti tombol "Buat SKP Periodik" di kipApp, dengan RK yang sama seperti triwulan sebelumnya. Ini hanya jalan bila SKP Tahunan sudah berstatus Ditetapkan.',
                    'Kalau SKP triwulan Anda sudah ada tetapi belum memuat RK kegiatan ini, Kinetik menambahkan RK itu dari SKP Tahunan, seperti mengedit SKP Periodik di kipApp. RK yang sudah ada tidak berubah.',
                    'Jika belum terkirim, alasannya tampil di detail kegiatan, misalnya "SKP Tahunan belum Ditetapkan". Kinetik mencoba lagi pada jam berikutnya sampai tanggal selesai dan memberi tahu Anda satu kali lewat lonceng.',
                    'Kinetik tidak mengirim kegiatan sebelum tanggal mulainya, dan tidak mengirim yang belum lengkap. Tombol "Kirim ke kipApp" aktif bila tanggal mulai sudah tiba dan progres, capaian, serta bukti dukung terisi.',
                    'Selesai mengerjakan? Tekan "Tandai selesai". Uraian capaian wajib minimal 10 karakter saat ini. Kinetik mengirim progres 100%, uraian capaian dan tautan bukti ke kipApp. Tombol "Simpan realisasi" menyimpan di Kinetik, dan untuk kegiatan yang sudah ada di kipApp dan belum dikirim ke atasan ikut memperbarui kipApp (progres, uraian, bukti dukung). Kegiatan yang belum ada di kipApp langsung dikirim begitu simpanan membuatnya lengkap (progres 100, uraian, bukti dukung, dan capaian bila ada target), tanpa menunggu pengiriman tiap jam. Kegiatan yang gagal dikirim karena login kipApp kedaluwarsa dicoba lagi setelah pemiliknya masuk kembali. Pesan sesudah menyimpan menyebut hasilnya: kipApp ikut diperbarui, atau tersimpan di Kinetik saja beserta alasannya. Uraian dan bukti dukung yang diketik langsung di kipApp mengisi Kinetik saat sinkron bila di Kinetik masih kosong.',
                    'Kegiatan yang sudah ada di kipApp dan belum dikirim ke atasan bisa diubah, atau dihapus lewat tombol "Hapus kegiatan" di detail. Kinetik menghapusnya juga di kipApp, setelah bertanya dulu. Kegiatan yang sudah dikirim ke atasan tidak bisa dihapus: tekan "Batalkan kirim" dulu, lalu hapus. Jika SKP triwulan sudah dikirim untuk dinilai, kegiatan terkunci dan alasannya tampil di detail.',
                    'RK dan projek bisa diganti selama kegiatan belum dikirim ke atasan, sama seperti form ubah di kipApp sendiri: buka kegiatannya, tekan "Ganti RK", lalu pilih RK lain. Untuk kegiatan yang sudah ada di kipApp, RK baru harus termasuk RK Anda di SKP triwulan itu. Bila tidak, Kinetik menolak dan tidak mengubah apa pun. Kegiatan yang sudah dikirim ke atasan tidak bisa berganti RK: tekan "Batalkan kirim" dulu.',
                    'Kirim ke atasan tidak ada di Rekap Mingguan. Pengiriman dilakukan sekali per triwulan di menu Kegiatan → Kirim ke Atasan, supaya orang tidak mengirim sebelum lengkap. Di Rekap Mingguan, centang beberapa kegiatan lalu pilih "Aksi kegiatan terpilih" → "Kirim ke kipApp" untuk mengirim kegiatan yang sudah lengkap ke kipApp.',
                    'Jika progres diubah langsung di kipApp, Kinetik mengikuti kipApp pada sinkronisasi berikutnya.',
                ],
                tip: 'Jika kata sandi SSO Anda ditolak BPS, Kinetik berhenti mengakses kipApp atas nama Anda dan meminta Anda memasukkannya lagi saat masuk. Kinetik tidak mencoba ulang dengan kata sandi yang sama.',
            },
            {
                title: 'Kirim satu triwulan ke atasan',
                where: 'Menu Kegiatan → Kirim ke Atasan',
                route: 'quarter-send.index',
                body: [
                    'Halaman ini seperti halaman SKP triwulan di kipApp: semua kegiatan Anda pada satu triwulan, dikelompokkan per RK. Pilih triwulan di atas (TW I sampai IV). Empat kartu menunjukkan jumlah kegiatan yang belum dikirim, sudah dikirim, belum bisa dikirim, dan semuanya. Klik kartu untuk menyaring.',
                    'Centang kegiatan yang mau dikirim, atau tekan "Pilih semua yang belum dikirim". Bila ada yang belum punya capaian, bukti dukung, uraian, atau progres 100, halaman menampilkan daftarnya lebih dulu, karena kegiatan yang sudah dikirim tidak bisa diubah. Lalu tekan "Kirim ke atasan". Kinetik menanyakan dulu, lalu mengirim ke kipApp dalam beberapa kelompok bila jumlahnya banyak. "Batalkan kirim" menarik kembali kegiatan yang sudah dikirim selama SKP triwulannya masih Sedang dibuat.',
                    'Kegiatan yang belum bisa dikirim punya dua sebab: belum ada di kipApp (kirim dulu dari Rekap Mingguan), atau SKP triwulannya sudah dikunci. Menu ini hanya muncul bila admin menyalakan "Kirim kegiatan ke atasan".',
                ],
                tip: 'Mengirim kegiatan ke atasan bukan mengirim SKP untuk dinilai. SKP triwulan dikirim di menu SKP Saya.',
            },
            {
                title: 'Lihat hasilnya',
                where: 'Menu Kegiatan → Pantau Kegiatan, dan menu Rekap Tim',
                route: 'kip-activities.index',
                body: [
                    'Pantau Kegiatan hanya untuk memantau: halaman ini menampilkan semua kegiatan kipApp Anda dan tidak dipakai untuk mengisi. Saring per Minggu, Bulan atau Triwulan, lalu pindah periode dengan ‹ dan ›.',
                    'Ringkasan Rapat di Beranda menunjukkan rapat mingguan dan bulanan tim Anda: Lengkap, Sebagian, atau Belum. Klik satu status untuk membaca hasil rapatnya: tabel satu baris per RK berisi parafrase PJ, target, capaian dan persen, ditambah bukti rapat.',
                    'Di Rekap Tim, anggota hanya melihat hasil rapat itu. Kegiatan dan isian anggota lain tidak ditampilkan. Hanya PJ yang menulis parafrase.',
                ],
            },
            {
                title: 'Pantau SKP Anda',
                where: 'Menu Kegiatan → SKP Saya',
                route: 'skp.index',
                body: [
                    'Halaman ini menunjukkan SKP Anda di kipApp: SKP Tahunan dengan tahapnya, dan SKP tiap triwulan dengan status, jumlah RK dan jumlah kegiatan.',
                    'Urutan di kipApp: (1) RK dan IKI SKP Tahunan dibuat di kipApp, (2) SKP Tahunan dikirim lalu ditetapkan atasan, (3) SKP triwulan dibuat, (4) kegiatan diisi, (5) SKP triwulan dikirim dan dinilai atasan.',
                    'Atasan menetapkan SKP Tahunan di halaman Persetujuan SKP Kinetik atau di kipApp, dengan akun kipApp-nya sendiri. Menilai SKP triwulan di Persetujuan SKP. Anda melihat statusnya di sini dan mendapat notifikasi saat atasan menetapkan atau mengembalikan.',
                    'SKP Tahunan yang masih Sedang dibuat bisa dikirim dari halaman ini dengan "Kirim untuk ditetapkan". Syaratnya sama seperti kipApp: minimal satu RK dan setiap RK punya IKI.',
                ],
                shot: SHOTS.skpSaya,
            },
            {
                title: 'Buat SKP triwulan',
                where: 'Menu Kegiatan → SKP Saya → Buat SKP TW',
                route: 'skp.index',
                body: [
                    'Awal triwulan baru belum ada SKP-nya. Tekan "Buat SKP TW n", centang RK SKP Tahunan yang Anda kerjakan triwulan itu, lalu "Buat SKP".',
                    'Tombolnya hanya muncul bila SKP Tahunan sudah Ditetapkan atasan. Kalau belum, halaman memberi tahu alasannya.',
                    'Selama SKP triwulan masih Sedang dibuat, "Tambah RK" menambahkan RK dari SKP Tahunan. RK yang sudah ada tidak berubah.',
                    'Kalau Anda lupa, Kinetik membuat SKP triwulan dan menambah RK yang kurang sendiri saat mengirim kegiatan ke kipApp (lihat langkah Kirim ke kipApp).',
                ],
                shot: SHOTS.skpBuatTriwulan,
            },
            {
                title: 'Tambah IKI pada RK',
                where: 'Menu Kegiatan → SKP Saya → kartu triwulan → RK dan IKI',
                route: 'skp.index',
                body: [
                    'SKP tidak bisa dikirim bila ada RK tanpa IKI. Buka "RK dan IKI" pada SKP yang masih Sedang dibuat, lalu tekan "Tambah IKI" di bawah RK yang belum punya.',
                    'Tulis IKI beserta targetnya dalam satu kalimat, misalnya "Persentase entri data selesai tepat waktu 95 persen". kipApp meminta minimal 10 huruf dan tidak punya kolom target terpisah, jadi target ikut di teksnya. Tombol Simpan IKI membuat IKI di kipApp dengan akun Anda.',
                    'IKI yang Anda tambah bisa dihapus dengan ikon tempat sampah. IKI yang datang dari atasan atau dari SKP Tahunan tidak bisa dihapus di sini.',
                    'Tambah dan hapus IKI sudah diuji langsung di kipApp (IKI uji dibuat, terbaca di kipApp, lalu dihapus). Fitur ini tetap bisa dimatikan admin di menu Fitur.',
                ],
                shot: SHOTS.skpIki,
                tip: 'Salah menulis IKI? Hapus lalu tambah lagi selama SKP masih Sedang dibuat.',
            },
            {
                title: 'Kirim SKP triwulan untuk dinilai',
                where: 'Menu Kegiatan → SKP Saya → Kirim untuk dinilai',
                route: 'skp.index',
                body: [
                    'Setelah kegiatan triwulan itu lengkap dan sudah dikirim ke atasan, tekan "Kirim untuk dinilai". Kinetik bertanya dulu.',
                    'Statusnya menjadi "Sedang diperiksa". Selama itu RK dan kegiatan terkunci seperti di kipApp.',
                    '"Batalkan pengiriman" mengembalikan SKP ke Sedang dibuat, selama atasan belum menilai.',
                    'Kirim dan batal kirim SKP triwulan sudah diuji langsung di kipApp. Kirim SKP Tahunan untuk ditetapkan mengikuti kode kipApp tetapi belum diuji langsung, karena SKP Tahunan kami semuanya sudah Ditetapkan.',
                ],
                shot: SHOTS.skpKirim,
                tip: 'Penetapan dan penilaian dilakukan atasan sendiri dengan akun kipApp-nya. Penilaian triwulan di kipApp.',
            },
            {
                title: 'Pantau Angka Kredit',
                where: 'Menu Kegiatan → Angka Kredit Saya',
                route: 'credit.mine',
                body: [
                    'Halaman ini menghitung Angka Kredit (AK) dari golongan dan predikat SKP Anda di kipApp.',
                    'Batang kemajuan menunjukkan AK yang sudah terkumpul menuju pangkat atau jenjang berikutnya, sisa AK, dan perkiraan jumlah triwulan lagi dengan predikat Baik.',
                    'Triwulan yang belum dinilai, atau bulan yang dinilai tanpa predikat, ditandai Estimasi dan dihitung dengan predikat Baik.',
                    'Kolom Perhitungan menunjukkan rumusnya, misalnya "Ahli Madya: 3 bln × 37,5 ÷ 12 × 100%" = 9,375 AK.',
                    'kipApp tidak menyimpan AK yang sudah ditetapkan sebelumnya. Klik "Isi AK dari PAK", lalu isi AK dan tanggal pada PAK terakhir Anda (atau PAK integrasi 2022). AK sesudah tanggal itu tetap dihitung dari predikat kipApp.',
                    'Nilai yang Anda isi ditandai "Diisi sendiri" sampai admin mencocokkannya dengan dokumen PAK.',
                    'Begitu Anda naik pangkat, hitungan AK mulai lagi dari 0 sejak bulan kenaikan itu. AK dari pangkat sebelumnya tidak ikut dijumlahkan, karena kenaikan pangkat memakai AK baru. Halaman ini memberi catatan tanggal mulainya.',
                    'Angka ini perkiraan, bukan angka resmi. Dasar hitungnya PermenPANRB Nomor 1 Tahun 2023 (Pasal 37 dan Lampiran A) dan PerBKN Nomor 3 Tahun 2023 (Pasal 13): koefisien tahunan jenjang dibagi 12, dikali persentase predikat, per bulan.',
                    'Yang belum ikut dihitung: AK dari luar predikat SKP (misalnya pendidikan, pengembangan kompetensi, penyesuaian atau konversi jabatan), serta pengurangan. AK di bulan sebelum ada SKP di kipApp hanya masuk lewat "AK dari PAK". Angka resmi tetap dari PAK yang ditetapkan Tim Penilai.',
                    'Angka tiap triwulan bisa diubah dengan tombol "Ubah" bila Anda punya dasar, misalnya dokumen PAK. Alasan wajib diisi. Triwulan itu ditandai "Diubah manual" dan PJ serta pimpinan melihat siapa yang mengubah, kapan, alasannya, dan angka hitungan otomatisnya. "Pakai hitungan otomatis" mengembalikannya.',
                ],
                shot: SHOTS.anggotaAngkaKredit,
                tip: 'Angka ini untuk memantau. Usulan kenaikan pangkat tetap memakai PAK resmi.',
            },
        ],
    },
    {
        role: 'pj',
        label: 'PJ / Ketua Tim',
        summary: 'Anda menyiapkan bahan rapat tim dan rapat dengan pimpinan dari Rekap Mingguan anggota, menulis parafrase per RK, lalu mengunci.',
        replaces: 'Sheet "Rapat Mingguan" dan "Rapat Bulanan"',
        steps: [
            SSO_LOGIN_STEP,
            PAGE_TOUR_STEP,
            {
                title: 'Isi Rekap Mingguan Anda sendiri',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: ['Sama seperti anggota. Halaman ini hanya menampilkan kegiatan Anda sendiri; rekap anggota ada di menu Rekap Tim.'],
            },
            {
                title: 'Buka Rekap Tim mingguan',
                where: 'Menu Rekap Tim → Rekap Tim → Mingguan',
                route: 'team-recap.weekly',
                body: [
                    'Halaman ini dibuka dengan daftar bulan untuk tim yang dipilih. Pilih satu bulan, lalu satu minggu. Tiap baris menunjukkan jumlah kegiatan, berapa RK yang punya kegiatan, berapa yang parafrasenya sudah ditulis, dan statusnya (Belum ada kegiatan, Belum ditulis, Sedang ditulis, atau Selesai ditulis; artinya ada di bagian "Arti status"). Pemilih tim dan tahun ada di atas daftar.',
                    'Di dalam sebuah minggu, parafrase RK ada di paling atas karena itulah isi rapat. Di bawahnya ada dua tabel seperti Excel lama, "Realisasi minggu lalu" dan "Rencana minggu ini", lalu bagian "Kelengkapan anggota" yang menunjukkan siapa yang belum mengisi capaian. Tiap bagian bisa dilipat dengan menekan judulnya. Ingatkan anggota sebelum rapat.',
                ],
            },
            {
                title: 'Lihat rencana minggu ini dari anggota',
                where: 'Menu Rekap Tim → Rekap Tim → Mingguan → pilih bulan dan minggu',
                body: [
                    'Tiap tabel rapat memuat, per baris, anggota, kegiatan, tanggal, target, realisasi, persen, progres, dan permasalahan. Anggota yang belum membuat rencana tetap tampil dengan tulisan "Belum membuat rencana". PJ menekan sebuah baris untuk mengisi realisasi dan permasalahan saat rapat. Kegiatan yang berjalan beberapa minggu tampil bertanda "Lanjutan" dengan realisasi minggu itu.',
                    'Klik satu baris untuk membuka semua isinya (target, capaian, uraian, bukti dukung dan status). Tampilan ini hanya baca. Anggota mengubah kegiatannya di Rekap Mingguan mereka sendiri.',
                    'Untuk membahas capaian minggu lalu, kembali ke daftar minggu dan buka minggu sebelumnya.',
                ],
            },
            {
                title: 'Baca kontribusi anggota dan tulis parafrase',
                body: [
                    'Tiap RK tampil sebagai kartu ringkas: parafrase PJ, target RK, capaian RK, status parafrase, dan tabel pendek anggota yang mengerjakannya. Tekan "Isi parafrase" (atau "Ubah parafrase") untuk membuka popup. Mengklik nama anggota di tabel juga membuka popup itu untuk anggota tersebut.',
                    'Di kiri popup ada kegiatan anggota untuk RK itu, satu baris per anggota. Klik nama untuk membukanya. Kolom cari dan "Belum dibaca saja" membantu menelusuri tim yang besar; "Dibaca n/m" menghitung yang sudah Anda buka. Filter "Lihat" menampilkan satu anggota saja.',
                    'Di kanan ada kotak Parafrase PJ yang kosong. Tulis sendiri, atau tekan "Sisipkan" pada satu kalimat anggota untuk mengambilnya. Kinetik tidak mengisi otomatis dari semua anggota.',
                    'Target dan capaian setiap anggota berasal dari Rekap Mingguan mereka dan tidak dapat diubah di sini.',
                ],
            },
            {
                title: 'Isi target, satuan dan capaian RK, lalu simpan',
                body: [
                    'Masih di popup yang sama, isi satu Target (angka), satu Satuan dan satu Capaian (angka) untuk seluruh RK. Persentase capaian dihitung otomatis.',
                    '"Simpan" menyimpan dan menutup popup; hasilnya langsung tampil di kartu RK. Tidak ada kunci: parafrase, target dan capaian boleh diubah lagi kapan saja.',
                    'Hanya PJ yang bisa menulis. PIC RK dan anggota lain hanya membaca.',
                ],
                tip: 'Parafrase dibaca semua anggota tim dan pimpinan sebagai hasil rapat. Anggota melihatnya dalam bentuk tabel, tanpa kegiatan anggota lain.',
            },
            {
                title: 'Tambah bukti rapat',
                body: ['Tambahkan link Notula, Dokumentasi (foto) dan Daftar Hadir rapat tim.'],
            },
            {
                title: 'Rekap bulanan dan triwulanan (FRA)',
                where: 'Menu Rekap Tim → Rekap Tim → Bulanan atau Triwulanan (FRA)',
                route: 'team-recap.monthly',
                body: [
                    'Halaman bulanan dibuka dengan daftar Januari sampai Desember. Pilih satu bulan untuk membuka rekapnya; tombol "Semua bulan" membawa Anda kembali ke daftar. Rekap sebuah bulan memakai kartu dan popup yang sama dengan mingguan, per RK. Parafrase mingguan bulan itu tampil di popup sebagai bahan, lengkap dengan tombol "Sisipkan".',
                    'Halaman triwulanan (FRA) dibuka dengan empat triwulan dalam setahun. Pilih satu untuk membuka rekapnya. Di dalamnya ada satu baris per RK dengan target dan capaian dari rekap bulanan. Jika bulanan belum diisi, angkanya dijumlahkan dari kegiatan anggota.',
                    'Buka panel sebuah baris untuk menulis uraian, permasalahan, solusi dan RTL. Isi juga link bukti tindak lanjut, PIC dan batas waktu.',
                    'Konfirmasi setiap baris. "Konfirmasi semua capaian 100%" mengonfirmasi semua baris yang sudah 100% sekaligus. Kunci triwulan setelah rapat selesai; tombolnya ada di bagian atas halaman.',
                ],
                tip: 'Parafrase, konfirmasi, bukti dan kunci triwulan hanya oleh PJ.',
            },
            {
                title: 'Unduh Excel untuk rapat',
                body: ['"Unduh Excel" menghasilkan satu sheet dengan format Rapat Mingguan / Rapat Bulanan / FRA untuk semua tim yang bisa Anda lihat.'],
            },
            {
                title: 'Isi Kertas Kerja FRA tiap bulan',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Kertas Kerja FRA memuat IKU dan proksi tingkat provinsi (sheet LK_Prov). Halaman ini bulanan: satu tabel per indikator, kolom Januari sampai Desember. Indikator yang timnya Anda pimpin bisa diisi.',
                    'Ketik X dan Y tiap bulan (untuk indikator bukan persen, satu nilai), lalu tekan "Simpan bulanan". Kinetik menghitung persen bulan itu dan angka kumulatif dari Januari, dengan rumus Kertas Kerja.',
                    'Warna: hijau bila target tercapai, kuning bila belum, merah bila kosong atau nol pada bulan yang sudah lewat, abu-abu untuk bulan yang belum tiba. Kotak berbingkai adalah bulan yang dipilih di kiri atas.',
                    'Tekan "Analisis (bulan)" untuk menulis kendala, solusi, rencana tindak lanjut, PIC, batas waktu dan link bukti. Untuk Excel triwulanan, analisis triwulan disusun dari ketiga bulannya: kendala, solusi, dan rencana tindak lanjut ditulis berurutan ("Jan: …", "Feb: …", "Mar: …"), PIC dan batas waktu diambil dari bulan terakhir yang mengisi, dan semua link bukti dipakai.',
                    'Di bawah baris Kumulatif ada baris "% terhadap target triwulan": angka kumulatif dibagi alokasi triwulan bulan itu, supaya terlihat apakah target triwulan akan tercapai sebelum triwulannya selesai.',
                    'Tab "Analisis TW" di atas halaman menampilkan dua versi dari data yang sama. Versi sistem disusun dari ketiga bulan dan hanya bisa dibaca. Versi sunting dimulai dari salinan versi sistem: PJ tim pemilik merapikan atau menulis ulang kendala, solusi, tindak lanjut, PIC, batas waktu, dan link, lalu menekan "Simpan versi sunting". File Excel triwulanan memakai versi sunting bila ada isinya, dan versi sistem bila tidak. Kolom yang dikosongkan ikut versi sistem lagi, dan "Kembalikan ke versi sistem" menghapus semua suntingan.',
                    'Tab "Lembar seperti Excel" menampilkan semua indikator dalam satu lembar panjang seperti Excel LK_Prov: gulir ke bawah dan ke samping, baris judul serta kolom Kode dan Indikator tetap terlihat. Lembar ini hanya untuk dibaca dan memuat angka triwulan yang dipilih; mengisi tetap di tab lain. Tombol "Unduh Excel" menghasilkan bentuk yang sama.',
                ],
                shot: SHOTS.fraBulanan,
                tip: 'Tombol Analisis tidak muncul pada indikator tertentu? Minta admin menetapkan tim Anda sebagai pemilik indikator itu.',
            },
            {
                title: 'Lihat progres pekerjaan per IKU',
                where: 'Menu Rekap Tim → Progres per IKU',
                route: 'iku-progress.index',
                body: [
                    'Halaman ini menunjukkan seberapa jauh pekerjaan di bawah tiap IKU: capaian kegiatan dibagi targetnya (paling tinggi 100%), dirata-rata per bulan lewat kegiatan, RK, dan projek yang terhubung ke IKU. Tab "Per tim" memecah hal yang sama per tim.',
                    'Ini progres pekerjaan, bukan realisasi IKU. IKU punya data dasar dan rumusnya sendiri (misalnya jumlah publikasi terbit dibanding rencana), jadi realisasi resmi tetap di Kertas Kerja FRA. Satu IKU bisa dikerjakan beberapa tim: kolom "Tim terkait" memuat semua tim yang punya projek di bawahnya.',
                ],
                tip: 'IKU yang kosong biasanya belum ada kegiatan dengan target dan capaian, atau projeknya belum dihubungkan ke IKU di data master. Tab "Per tim" mendaftar semua tim: kolom "Target dan capaian terisi" menunjukkan berapa kegiatan yang sudah lengkap dan siapa yang belum mengisi.',
            },
            {
                title: 'Tetapkan atau kembalikan SKP anggota',
                where: 'Menu Rekap Tim → Persetujuan SKP',
                route: 'skp.approvals',
                body: [
                    'Di kipApp Anda adalah atasan anggota tim, jadi SKP anggota menunggu Anda. Saat anggota mengirim SKP lewat Kinetik, Anda mendapat notifikasi di lonceng.',
                    'Halaman Persetujuan SKP mendaftar SKP anggota yang menunggu: SKP Tahunan yang menunggu ditetapkan dan SKP triwulan yang menunggu dinilai. Menunya hanya muncul bila ada SKP yang atasannya Anda.',
                    '"Tetapkan" menetapkan SKP Tahunan anggota. "Kembalikan" mengirim SKP kembali ke anggota supaya diperbaiki. Kinetik bertanya dulu, lalu bekerja dengan akun kipApp Anda sendiri, jadi Anda harus sudah masuk ke Kinetik dengan kata sandi SSO.',
                    'SKP triwulan dinilai lewat tombol "Nilai", langkahnya sama dengan penilaian oleh pimpinan. SKP Tahunan Anda sendiri ditetapkan oleh pimpinan, bukan oleh Anda.',
                    'Anggota mendapat notifikasi saat SKP-nya ditetapkan atau dikembalikan. Tetapkan dan Kembalikan mengikuti kode kipApp dan belum diuji langsung, karena semua SKP Tahunan kantor ini sudah Ditetapkan.',
                ],
                shot: SHOTS.skpPersetujuanPj,
                tip: 'Kalau daftar kosong, tidak ada SKP anggota yang menunggu Anda.',
            },
            {
                title: 'Dari mana IKU dan PK berasal',
                where: 'Menu Data Master → IKU',
                route: 'performance-indicators.index',
                body: [
                    'IKU di Kinetik dibaca dari kipApp saat sinkronisasi struktur: Sasaran dari RK Kepala, lalu IKI pada RK itu menjadi IKU yang didukung timnya. IKU yang Anda tambah sendiri di halaman ini hanya tersimpan di Kinetik dan tidak dikirim ke kipApp.',
                    'Perjanjian Kinerja (Renstra, tujuan, sasaran dan indikator) tidak bisa dibuat di kipApp oleh pegawai: halamannya hanya untuk dibaca, karena PK ditetapkan di aplikasi SINERGI. Karena itu Kinetik juga tidak membuatnya.',
                    'IKI sebuah RK dibuat di kipApp, atau di Kinetik lewat SKP Saya bila fitur IKI menyala. Bila IKU atau IKI di Kinetik tampak berbeda dari kipApp, jalankan Sinkronisasi Struktur dan anggap kipApp yang benar.',
                ],
                tip: 'IKU tidak ada atau salah? Periksa dulu di kipApp dan SINERGI, lalu minta admin menjalankan Sinkronisasi Struktur.',
            },
            {
                title: 'Buat proyek dan RK sekaligus di kipApp',
                where: 'Menu Data Master → Proyek → Tambah Proyek, lalu Rencana Kinerja (RK) → Tambah RK',
                route: 'projects.create',
                body: [
                    'Proyek dan RK Anggota yang Anda buat di Kinetik bisa dibuat juga di kipApp, dengan akun kipApp Anda sendiri. Admin bisa mematikannya di menu Fitur. Saat mati, proyek dan RK hanya tersimpan di Kinetik dan kipApp tetap diisi seperti biasa.',
                    'Di form Tambah Proyek, setelah memilih tim, pilih RK Ketua dari daftar kipApp. Belum ada? Tekan "RK Ketua baru", isi rencana kinerja ketua dan pilih IKI JPT-nya. Proyek dibuat di kipApp di bawah RK Ketua itu, lengkap dengan anggotanya yang sudah terhubung ke kipApp.',
                    'Tambah RK membuat RK Anggota di kipApp untuk PIC-nya di proyek itu. PIC harus anggota proyek. Bila kipApp menolak, data tetap tersimpan di Kinetik dan muncul tombol "Kirim ke kipApp" di daftar untuk mengulang.',
                    'Pembuatan ini mengikuti kode kipApp sendiri dan belum diuji langsung. Periksa hasil pertama di kipApp.',
                ],
                shot: SHOTS.proyekBaruKipapp,
                tip: 'Kolom RK Ketua boleh dikosongkan bila proyek hanya perlu ada di Kinetik.',
            },
            {
                title: 'Bandingkan tim Anda di Review Bersama',
                where: 'Menu Rekap Tim → Review Bersama',
                route: 'team-recap.overview',
                body: [
                    'PJ juga punya Review Bersama, dengan tampilan yang sama seperti pimpinan, untuk semua tim (hanya baca). Pilih Mingguan, Bulanan, atau Triwulanan, lalu baca capaian, dan baris yang sudah ditulis parafrasenya. Tombol "Unduh Excel semua tim" memuat semua tim dan kolom Permasalahan.',
                    'Klik judul kolom Capaian untuk mengurutkan. Ringkasan Rapat di Beranda Anda menunjukkan rekap mingguan dan bulanan tim Anda sudah lengkap, sebagian, atau belum, dan berapa bukti rapat yang sudah dilampirkan.',
                    'Kepatuhan Rencana menampilkan ringkasan dulu (siapa yang belum membuat rencana minggu ini), lalu tabel lengkap bila Anda membukanya.',
                ],
                shot: SHOTS.pimpinanReviewBersama,
            },
            {
                title: 'Pantau Angka Kredit anggota',
                where: 'Menu Rekap Tim → Angka Kredit Tim',
                route: 'credit.team',
                body: [
                    'Daftar anggota diurutkan dari yang paling dekat ke kenaikan: Siap diusulkan, AK cukup tetapi menunggu 2 tahun, Hampir (paling lama 2 triwulan lagi), lalu Berjalan.',
                    'Pimpinan dan admin melihat semua tim. PJ melihat anggota tim yang dipimpinnya.',
                ],
                shot: SHOTS.pjAngkaKreditTim,
            },
        ],
    },
    {
        role: 'pimpinan',
        label: 'Pimpinan',
        summary: 'Anda membaca rekap seluruh kantor per minggu, bulan atau triwulan. Isi rekap diubah oleh PJ, bukan oleh pimpinan.',
        replaces: 'Membaca sheet "Rapat Mingguan" dan "Rapat Bulanan"',
        steps: [
            SSO_LOGIN_STEP,
            PAGE_TOUR_STEP,
            {
                title: 'Tetapkan atau kembalikan SKP',
                where: 'Menu Rekap Tim → Persetujuan SKP',
                route: 'skp.approvals',
                body: [
                    'Saat pegawai mengirim SKP lewat Kinetik, Anda mendapat notifikasi. Halaman ini mendaftar SKP yang atasannya Anda dan sedang menunggu: SKP Tahunan yang menunggu ditetapkan, dan SKP triwulan yang menunggu dinilai.',
                    '"Tetapkan" menetapkan SKP Tahunan. "Kembalikan" mengirim SKP kembali ke pegawai. Kinetik bertanya dulu, lalu bekerja dengan akun kipApp Anda sendiri, jadi Anda harus sudah masuk ke Kinetik dengan kata sandi SSO.',
                    'Klik nama pegawai pada SKP triwulan untuk membuka bahan penilaian: tiap RK dengan jumlah kegiatan, yang selesai, yang terkirim ke kipApp, capaian terhadap target, dan catatan capaian. Data ini dari Kinetik, jadi Anda menilai dengan bukti di depan mata.',
                    'Tombol "Nilai" membuka halaman Nilai SKP, dijelaskan di langkah berikut.',
                    'Daftar diperbarui tiap pagi dan saat pegawai mengirim lewat Kinetik. SKP yang dikirim langsung dari kipApp baru tampil pada pembaruan berikutnya.',
                    'Tetapkan dan Kembalikan mengikuti kode kipApp sendiri. Kami belum menguji penetapan langsung karena semua SKP Tahunan kantor ini sudah Ditetapkan.',
                ],
                shot: SHOTS.skpPersetujuan,
                tip: 'Jika daftar kosong, tidak ada SKP yang menunggu Anda.',
            },
            {
                title: 'Nilai SKP triwulan',
                where: 'Persetujuan SKP → Nilai',
                route: 'skp.approvals',
                body: [
                    'Halaman Nilai SKP menampilkan bahan penilaian dari Kinetik di atas, lalu satu baris untuk tiap RK dan satu baris untuk tiap dari tujuh perilaku BerAKHLAK. Isi nilai 0 sampai 100 dan umpan balik bila perlu, lalu tekan Simpan. Nilai langsung masuk ke kipApp dengan akun kipApp Anda.',
                    'Tingkat (di bawah, sesuai, atau di atas ekspektasi) muncul sendiri mengikuti tabel kipApp. Untuk RK: 99 ke atas di atas ekspektasi, 80 sampai 98 sesuai. Untuk perilaku: 91 ke atas dan 80 sampai 90.',
                    '"Selesai menilai" baru aktif setelah semua RK dan perilaku punya nilai. Setelah itu SKP menjadi Dinilai dan pegawai mendapat notifikasi.',
                    'Halaman ini mengikuti kode kipApp dan belum diuji dengan nilai asli. Isi SKP pertama bersama tim pengembang, dan periksa hasilnya di kipApp. kipApp menolak penyelesaian bila rata-rata nilai perilaku melewati batas bagi pegawai yang punya riwayat hukuman disiplin.',
                ],
                shot: SHOTS.skpNilai,
                tip: 'Salah isi nilai? Simpan lagi baris itu sebelum menekan Selesai menilai.',
            },
            {
                title: 'Baca Beranda dan Monitoring',
                where: 'Menu Beranda dan Monitoring',
                route: 'dashboard',
                body: [
                    'Beranda menjawab empat pertanyaan untuk minggu ini: siapa belum membuat rencana, siapa belum mengirim kegiatan ke atasan, tim mana belum punya rekap rapat mingguan, dan SKP mana menunggu Anda. Nama yang belum ditulis langsung di kotaknya.',
                    'Capaian per tim dan peringkat pegawai ada di menu Monitoring. Konsistensi perencanaan menghitung berapa dari 8 minggu terakhir seorang pegawai membuat rencana. Angka ini tidak menunjukkan beban kerja: satu proyek besar bisa lebih berat daripada lima proyek kecil.',
                ],
                shot: SHOTS.pimpinanBeranda,
                tip: 'Kotak yang hijau berarti tidak ada yang perlu ditindaklanjuti.',
            },
            {
                title: 'Monitoring: capaian dan konsistensi',
                where: 'Menu Monitoring',
                route: 'monitoring',
                body: ['Pilih bulan dan tahun di kiri atas. Grafik Konsistensi Perencanaan bisa diganti antara Tertinggi dan Terendah.'],
                shot: SHOTS.pimpinanMonitoring,
            },
            {
                title: 'Buka Review Bersama',
                where: 'Menu Rekap Tim → Review Bersama',
                route: 'team-recap.overview',
                body: [
                    'Baris kecil di paling atas menunjukkan yang perlu dilihat lebih dulu, satu label untuk tiap hal beserta jumlahnya. Klik labelnya untuk membuka halamannya. Rinciannya ada di menu Monitoring.',
                    'Review Bersama untuk pimpinan selalu per bulan. Pindah bulan dengan ‹ dan ›. (PJ dan admin masih bisa memilih Mingguan, Bulanan atau Triwulanan.)',
                    'Satu deret angka merangkum kantor: tim yang punya rekap, rata-rata capaian, dan RK yang sudah ada parafrasenya.',
                    'Review Bersama juga ada di menu PJ: PJ melihat semua tim dengan tampilan yang sama, hanya baca.',
                ],
                shot: SHOTS.pimpinanReviewBersama,
            },
            {
                title: 'Baca tabel per tim',
                body: [
                    'Setiap baris adalah satu tim, dengan nama PJ di bawah namanya, rata-rata capaian, jumlah parafrase yang sudah ditulis, dan status.',
                    'Klik judul kolom Capaian untuk mengurutkan: pertama dari tertinggi, kedua dari terendah, ketiga kembali ke urutan awal. Tim tanpa data selalu di bawah.',
                    'Klik panah di depan nama tim untuk melihat semua projek tim itu beserta PIC / ketua projek, jumlah anggota, baris RK dan capaian per projek.',
                    'Pada periode mingguan ada kolom "Anggota lengkap".',
                    'Kolom Status: Tercapai (capaian 100% atau lebih), Progres (70% sampai di bawah 100%), Rendah (di bawah 70%).',
                    'Saring dengan pilihan tim dan tombol status di atas tabel, misalnya hanya tim yang Rendah.',
                    '"Belum ada data" berarti belum ada rekap atau kegiatan pada periode itu, bukan capaian 0%.',
                ],
                tip: 'Arti semua status ada di bagian "Arti status" di Buku Pedoman ini.',
            },
            {
                title: 'Buka rekap sebuah tim',
                body: ['Klik nama tim atau tanda panah untuk membaca rekap lengkap tim itu pada periode yang sama.'],
            },
            {
                title: 'Unduh Excel semua tim',
                body: ['"Unduh Excel semua tim" menghasilkan satu sheet untuk seluruh kantor, dengan format seperti spreadsheet rapat sebelumnya.'],
            },
            {
                title: 'Lihat ringkasan rapat tiap tim',
                where: 'Menu Rekap Tim → Ringkasan Rapat',
                route: 'team-recap.meetings',
                body: [
                    'Satu tabel untuk satu bulan: tiap baris satu tim, tiap kolom satu minggu, ditambah kolom Rapat bulanan. Pindah bulan dengan ‹ dan ›.',
                    'Lengkap berarti semua RK yang punya kegiatan sudah ada parafrasenya. Sebagian berarti baru sebagian. Belum berarti belum ada. Minggu yang belum terjadi tampil tanda strip, bukan Belum. Angka kecil di samping status adalah jumlah bukti rapat yang dilampirkan PJ.',
                    'Tiga angka di atas merangkum bulan itu: rekap mingguan lengkap, rekap bulanan lengkap, dan tim yang punya bukti rapat. Klik satu status untuk membuka rekap tim itu.',
                    'Teks parafrase PJ tidak ditampilkan kepada pimpinan. Pimpinan membaca angka, status, dan bukti rapat.',
                ],
                shot: SHOTS.ringkasanRapat,
                tip: 'Tim yang tidak mengunci rekap mingguan terlihat paling cepat di sini, sebelum rapat.',
            },
            {
                title: 'Pantau kepatuhan rencana',
                where: 'Menu Rekap Tim → Kepatuhan Rencana',
                route: 'plan-compliance.index',
                body: [
                    'Halaman ini membuka dengan ringkasan: berapa persen anggota yang sudah membuat rencana minggu ini, berapa persen rencana minggu lalu yang selesai, dan berapa anggota yang belum punya rencana.',
                    'Di bawahnya nama anggota yang belum membuat rencana, per tim. Batas membuat rencana adalah Senin pukul 09.00.',
                    'Tabel lengkap per tim dan minggu tersembunyi dulu. Tekan "Lihat tabel per tim dan minggu" untuk membukanya. Merah berarti belum ada rencana.',
                ],
                shot: SHOTS.kepatuhanRencana,
            },
            {
                title: 'Baca Kertas Kerja FRA dan unduh Excel',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Halaman ini menampilkan IKU dan proksi per sasaran dalam tabel bulanan: X, Y, persen tiap bulan dan angka kumulatif terhadap target tahunan, serta tim pemilik tiap indikator.',
                    'Kartu di atas menunjukkan Capaian IKU sampai bulan yang dipilih, NKO rata-rata capaian PK, dan predikat SAKIP. Isi Nilai SAKIP agar koreksi predikat ikut dihitung.',
                    '"Unduh Excel LK_Prov" menghasilkan lembar triwulanan untuk triwulan dari bulan yang dipilih, dengan urutan baris dan kolom Kertas Kerja. Pimpinan hanya membaca; pengisian dilakukan PJ tim pemilik.',
                ],
            },
            {
                title: 'Pantau Angka Kredit pegawai',
                where: 'Menu Rekap Tim → Angka Kredit Tim',
                route: 'credit.team',
                body: [
                    'Pilih "Semua tim" untuk melihat seluruh pegawai kantor, atau satu tim.',
                    'Pegawai yang "Siap diusulkan" sudah cukup AK dan sudah 2 tahun dalam golongannya. "Hampir" berarti paling lama 2 triwulan lagi.',
                    'Angka ini adalah pemantauan dari predikat SKP kipApp. Usulan kenaikan tetap memakai PAK resmi.',
                ],
                shot: SHOTS.pimpinanAngkaKredit,
            },
            {
                title: 'Lihat data master',
                where: 'Menu Data Master',
                body: ['Proyek, IKU dan Rencana Kinerja dapat dibaca untuk semua tim.'],
            },
        ],
    },
    {
        role: 'admin',
        label: 'Admin',
        summary: 'Anda menjaga data kipApp tetap tersinkron dan mengelola tim serta pegawai.',
        steps: [
            {
                title: 'Ambil token x-auth dari kipApp',
                body: [
                    'Buka Google Chrome, masuk ke https://kipapp.bps.go.id dan login SSO dengan akun yang punya hak admin kipApp.',
                    'Tekan F12 (Mac: Cmd + Option + I) untuk membuka DevTools, lalu pilih tab Network.',
                    'Tekan F5 untuk memuat ulang halaman agar request kipApp tercatat.',
                    'Ketik v1 di kolom filter, lalu klik request user.',
                    'Di tab Headers, bagian Request Headers, cari baris x-auth. Klik kanan, pilih Copy value.',
                    'Nilai yang disalin berbentuk "Bearer eyJ...". Boleh ditempel utuh, termasuk kata Bearer.',
                ],
                shot: SHOTS.devtoolsXauth,
                tip: 'Token berlaku sekitar 24 jam dan memberi akses ke data semua pegawai. Jangan kirim token lewat chat atau email; tempel langsung di Kinetik.',
            },
            {
                title: 'Periksa dan perbarui token kipApp',
                where: 'Menu Data Master → Integrasi kipApp',
                route: 'kip-integration.index',
                body: [
                    'Token admin kipApp berlaku sekitar 24 jam. Jika status menunjukkan "Token kedaluwarsa", sinkronisasi akan gagal.',
                    'Tempel token dari langkah 1 di kolom Token x-auth, lalu tekan Simpan Token. Status berubah menjadi berlaku, lengkap dengan waktu kedaluwarsa.',
                ],
                shot: SHOTS.adminIntegrasi,
            },
            {
                title: 'Bertindak sebagai pengguna lain',
                where: 'Menu Data Master → Lihat sebagai',
                route: 'admin.impersonate',
                body: [
                    'Pilih satu pegawai, PJ, atau pimpinan, lalu tekan "Lihat sebagai". Anda melihat aplikasi persis seperti yang ia lihat, dan bisa menyimpan, mengirim ke kipApp, atau menghapus atas namanya. Spanduk kuning di atas selalu mengingatkan Anda sedang bertindak sebagai siapa.',
                    'Setiap perubahan dicatat dengan nama Anda dan nama akun yang dipakai. Kata sandi, profil, login kipApp, dan penghapusan akun tidak bisa diubah dari sini. Akun admin tidak bisa dipilih.',
                    'Perubahan ke kipApp memakai sesi kipApp milik pegawai itu, jadi di kipApp tampil seolah ia sendiri yang mengerjakannya. Pakai untuk membantu pegawai yang kesulitan, dan beri tahu orangnya.',
                    'Tekan "Kembali ke admin" di spanduk untuk kembali sebagai admin.',
                ],
                tip: 'Mau hanya melihat? Cukup jangan menyimpan apa pun. Tidak ada yang berubah sampai Anda menekan tombol simpan atau kirim.',
            },
            {
                title: 'Nyalakan atau matikan fitur kipApp',
                where: 'Menu Data Master → Fitur',
                route: 'admin.features',
                body: [
                    'Halaman Fitur mendaftar fitur yang bekerja dengan kipApp: kirim kegiatan ke atasan, ubah dan hapus kegiatan setelah dikirim, Nilai SKP oleh atasan, dan pembuatan RK Ketua, proyek dan RK Anggota di kipApp.',
                    'Tekan Nyalakan atau Matikan. Perubahan berlaku segera untuk semua pengguna dan tidak perlu menyentuh server. Fitur bertanda "Belum diuji langsung" menulis data resmi di kipApp dan belum pernah dicoba di kipApp asli, jadi Kinetik bertanya dulu sebelum menyalakannya.',
                    'Pilihan dari halaman ini mengalahkan pengaturan server (.env). "Ikuti pengaturan server" menghapus pilihan itu.',
                ],
                shot: SHOTS.adminFitur,
                tip: 'Ada masalah setelah fitur dinyalakan? Matikan dari sini, data yang sudah masuk ke kipApp tidak ikut terhapus.',
            },
            {
                title: 'Pantau akun SSO pegawai',
                where: 'Menu Data Master → Integrasi kipApp, kartu 4',
                route: 'kip-integration.index',
                body: [
                    'Pegawai masuk dengan akun SSO BPS dan menghubungkannya pada login pertama. Kartu "Akun SSO belum terhubung" menampilkan nama pegawai yang belum melakukannya. Ingatkan mereka masuk ke Kinetik.',
                    'Selama belum terhubung, pegawai masih bisa masuk dengan kata sandi bawaan sampai tanggal KIP_DEFAULT_PASSWORD_UNTIL di file .env. Sesudah tanggal itu kata sandi bawaan ditolak.',
                    'Akun admin dan akun tanpa username SSO tetap memakai kata sandi Kinetik sendiri.',
                    'Kata sandi SSO dan token pegawai dienkripsi dengan KIP_CREDENTIAL_KEY di file .env. Jangan mengubah atau menghapus kunci ini: semua kata sandi tersimpan menjadi tidak terbaca.',
                ],
                tip: 'Token admin tetap diperlukan untuk Sinkronisasi Struktur dan sebagai cadangan. Sinkronisasi kegiatan pribadi memakai token pegawai itu sendiri bila masih berlaku.',
            },
            {
                title: 'Sinkronkan struktur, kegiatan, lalu Angka Kredit',
                body: [
                    'Jalankan berurutan: 1. Struktur, 2. Kegiatan, 3. Angka Kredit. Selama satu sinkronisasi berjalan, tombol lain tidak aktif.',
                    'Tekan Sinkronkan pada Sinkronisasi Struktur lebih dulu. Langkah ini menarik tim, projek, anggota dan RK, termasuk ID pegawai kipApp. Sinkronisasi juga membaca daftar RK Anggota tiap projek dari kipApp, supaya RK yang belum punya projek terisi projeknya sendiri. RK yang di kipApp tidak berada di projek mana pun (misalnya RK tambahan) tetap tanpa projek.',
                    'Setelah selesai, tekan Sinkronkan pada Sinkronisasi Kegiatan. Kegiatan semua bulan dalam periode ikut ditarik, termasuk yang sudah dikirim.',
                    'Keduanya juga berjalan otomatis setiap pagi: Struktur pukul 04.30 WITA dan Kegiatan pukul 05.00 WITA. Jadwal ini memakai Task Scheduler di panel hosting, yang menjalankan php artisan schedule:run setiap 5 menit.',
                    'Sinkronisasi Angka Kredit menarik golongan, jabatan dan predikat SKP semua tahun untuk halaman Angka Kredit. Jalankan sesudah Sinkronisasi Struktur. Proses ini memanggil kipApp sekitar tiga kali per pegawai, jadi perlu beberapa menit. Biarkan halaman tetap terbuka sampai selesai.',
                    'Sinkronisasi Angka Kredit juga berjalan otomatis setiap Senin pukul 05.30 WITA.',
                    'Token kipApp berlaku sekitar 24 jam. Kinetik mengirim notifikasi ke admin tiga jam sebelum token kedaluwarsa dan sekali lagi saat sudah kedaluwarsa. Simpan token baru di halaman Integrasi kipApp.',
                ],
                tip: 'Sinkronisasi hanya menambah dan memperbarui data. Kegiatan Anda, parafrase, konfirmasi, kunci triwulan dan bukti rapat tidak pernah dihapus. Satu pengecualian: anggota projek disamakan dengan daftar di kipApp.',
            },
            {
                title: 'Kelola tim dan pegawai',
                where: 'Menu Data Master → Tim Kerja / Pegawai',
                route: 'teams.index',
                body: [
                    'Pastikan setiap pegawai punya NIP Lama; NIP Lama adalah kunci untuk menarik kegiatan dari kipApp.',
                    'Kinetik hanya menyimpan pegawai BPS Provinsi Sulawesi Tengah. Setiap sinkronisasi memeriksa kantor pegawai di kipApp; pegawai kabupaten/kota atau provinsi lain otomatis dinonaktifkan dan dikeluarkan dari tim.',
                    'Ketua tim (PJ) diambil dari kipApp. Perbaiki di kipApp, lalu sinkronkan ulang.',
                ],
            },
            {
                title: 'Impor kegiatan dari file Excel',
                where: 'Menu Data Master → Impor Kegiatan',
                route: 'admin.import-kegiatan',
                body: [
                    'Untuk tim yang masih mencatat rencana di file Excel "Kegiatan Mingguan Anggota". Pilih minggu, pilih file, lalu tekan "Baca file". File dibaca di browser Anda, satu sheet per tim.',
                    'Unggah juga file Daftar Pegawai (ekspor NIP BPS, NIP, Nama) bila ada. Kinetik mencari NIP tiap nama di daftar itu, lalu mencocokkan pegawai lewat NIP, bukan lewat nama, jadi gelar yang berbeda atau nama kembar tidak jadi masalah. Tanpa daftar itu, Kinetik mencocokkan lewat nama tanpa gelar. Projek dicocokkan dari nama projek, dan RK dari projek itu. Bila RK tidak bisa ditentukan sendiri, pilih dari daftar di baris itu. Daftar diurutkan menurut kemiripan kata dengan uraian kegiatan: bila satu RK jelas paling mirip, Kinetik memilihnya dan memberi catatan "RK dipilih dari kemiripan dengan uraian" supaya Anda memeriksanya. RK milik pegawai sendiri, misalnya dari tim pusat, ikut ditawarkan. Baris yang pegawainya tidak ditemukan tidak bisa diimpor.',
                    'Kapan RK harus dipilih sendiri: nama projek di Excel sering berbeda dari nama projek di kipApp (misalnya "Penyusunan Daftar Risiko" di Excel, "Manajemen Risiko dalam ..." di kipApp). Kinetik mencoba mencocokkan lewat kata yang sama. Bila tidak ada satu projek yang jelas cocok, baris itu diberi catatan "Projek ... tidak ditemukan di tim pegawai ini", dan daftar RK berisi semua RK tim pegawai itu. Pilih RK yang paling sesuai. Satu RK hanya tampil sekali walau di kipApp ia tercatat di beberapa projek atau anggota.',
                    'Baris tanpa tanggal menjadi rencana Senin sampai Jumat minggu yang dipilih. Capaian diambil dari kolom Realisasi, dan progres dihitung dari capaian dibagi target. Kolom Permasalahan, Solusi, dan Rencana Tindak Lanjut tidak diimpor.',
                    'Baris yang mirip dengan kegiatan yang sudah ada, termasuk yang sudah dibuat pegawai di kipApp, diberi catatan "Mirip dengan kegiatan di kipApp" dan tidak dicentang. Mirip artinya pegawai yang sama, tanggal sama atau bersebelahan, dan uraian hampir sama. Angka yang berbeda dianggap kegiatan lain ("Mengikuti BRS 2" dan "Mengikuti BRS 5"). Centang barisnya bila memang kegiatan yang berbeda.',
                    'Periksa tabel, lalu tekan "Impor". Kegiatan yang sama (pegawai, tanggal, dan uraian) dilewati. Kegiatan masuk ke daftar pegawai sebagai rencana dan tidak dikirim ke kipApp. Pegawai mengirimnya sendiri dari detail kegiatan, saat sudah siap.',
                ],
                shot: SHOTS.imporKegiatan,
                tip: 'Sinkronkan RK dan projek dari kipApp dulu. Pencocokan RK memakai RK yang sudah ada di Kinetik.',
            },
            {
                title: 'Periksa data yang kehilangan hubungan',
                where: 'Menu Data Master → Integritas data',
                route: 'admin.data-integrity',
                body: [
                    'Halaman ini mendaftar data yang kehilangan hubungan penting: RK tanpa projek, kegiatan belum terkirim yang RK-nya tanpa projek, kegiatan tanpa RK, RK tanpa tim, projek tanpa RK Ketua, pegawai tanpa ID kipApp, tim tanpa ketua, dan pengiriman ke kipApp yang gagal. Tiap kartu menjelaskan mengapa itu penting, cara memperbaikinya, dan contoh barisnya.',
                    'Kinetik menahan pengiriman ke kipApp untuk kegiatan yang RK-nya seharusnya punya projek tetapi belum punya. Kegiatannya tetap tersimpan, dan alasannya tercatat sebagai pesan gagal kirim. Setelah projeknya jelas (lewat Sinkronisasi Struktur atau dipilih pada kegiatan), pengiriman berjalan lagi. RK yang di kipApp memang tidak berada di projek mana pun, misalnya RK tambahan, tidak dihitung sebagai masalah.',
                    'Kartu "Kegiatan ganda" mendaftar dua kegiatan satu pegawai pada hari yang sama dengan kata-kata yang sama, paling-paling beda salah ketik. Kegiatan yang beda tempat, angka, atau kata kerjanya (misalnya Kabupaten Tolitoli dan Kabupaten Sigi, atau Surat Masuk dan Surat Keluar) tidak dianggap ganda. Tekan "Tinjau pasangan dan minta tindakan": tiap pasangan menyarankan mana yang dipertahankan (yang punya capaian, bukti, atau sudah di kipApp) dan mana yang dihapus, beserta caranya. Admin tidak menghapus apa pun di sini. Centang pasangan lalu tekan "Minta tindakan", dan pegawainya menerima pemberitahuan di lonceng yang membuka minggunya. Status "Diminta" mencatat kapan dan oleh siapa; "Minta lagi" mengirim ulang. Bila setelah ditinjau ternyata dua pekerjaan yang berbeda, tekan "Bukan ganda" (per baris, atau centang beberapa): pasangan itu tidak muncul lagi di daftar maupun di angka pada menu.',
                ],
                tip: 'Buka halaman ini setelah Sinkronisasi Struktur. Kartu yang berubah menjadi "Aman" berarti hubungan datanya lengkap.',
            },
            {
                title: 'Baca laporan masalah dari pegawai',
                where: 'Menu Data Master → Laporan masalah',
                route: 'admin.bug-reports',
                body: [
                    'Setiap pegawai bisa menekan "Laporkan masalah" di bagian atas halaman mana pun. Laporan memuat ringkasan, cerita singkat, dan alamat halaman yang sedang dibuka. Admin menerima notifikasi di lonceng.',
                    'Di halaman Laporan masalah, ubah statusnya menjadi Baru, Sedang diperiksa, Selesai, atau Bukan masalah.',
                ],
            },
            {
                title: 'Tetapkan tim pemilik indikator FRA',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Setiap IKU dan proksi di Kertas Kerja FRA perlu tim pemilik. PJ tim itu yang mengisi X, Y dan analisis tiap bulan.',
                    'Pada kolom Tim pemilik, pilih tim dari daftar (bisa dicari). Indikator tanpa pemilik hanya bisa diisi admin.',
                    'Data awal berasal dari Kertas Kerja TW 2 2026. Jalankan seeder FraIndicatorSeeder satu kali setelah migrasi; menjalankannya lagi tidak menimpa angka yang sudah diisi.',
                ],
            },
            {
                title: 'Tunjuk admin kepegawaian',
                where: 'Menu Data Master → Pegawai',
                route: 'employees.index',
                body: [
                    'Urusan data pegawai dan Angka Kredit bisa dipegang petugas SDM tanpa menjadikannya admin penuh. Di baris pegawai, buka menu aksi lalu pilih "Jadikan admin kepegawaian". Pegawai itu harus sudah pernah masuk ke Kinetik.',
                    'Admin kepegawaian mengelola Pegawai, Tim Kerja, Impor AK dan perbandingan AK, Angka Kredit Tim (termasuk mengecek PAK), dan arsip kegiatan pegawai mana pun. Integrasi kipApp, Fitur, Lihat sebagai, dan laporan masalah tetap hanya untuk admin. Perannya tambahan: pegawai itu tetap memakai Kinetik seperti anggota biasa.',
                ],
            },
            {
                title: 'Impor AK dari rekapan Excel',
                where: 'Menu Data Master → Impor AK (admin atau admin kepegawaian)',
                route: 'admin.import-ak',
                body: [
                    'Dipakai sekali untuk memasukkan AK resmi dari sebelum kipApp. Unggah rekapan Excel AK, lalu tekan "Baca file". File dibaca di browser Anda, baru dikirim ke server saat pratinjau.',
                    'Pratinjau menampilkan tiap pegawai: baris yang cocok, baris yang ditahan, dan baris yang perlu Anda cek sendiri. Pegawai dicocokkan lewat NIP.',
                    'Setelah Anda menekan "Simpan", angka itu menjadi AK dasar pegawai. Kinetik menghitung sisanya dari hari sesudah tanggal dasar itu, memakai predikat kipApp.',
                    'Sebelum menyimpan, tekan "Bandingkan dengan hitungan Kinetik". Tabel yang muncul menaruh AK kantor tahun 2023, 2024, dan 2025 di samping hitungan Kinetik untuk tahun yang sama, dengan keterangan: Sama, Beda predikat (kantor memakai satu predikat setahun, kipApp predikat bulanan), atau Bulan kosong di kipApp. Pakai tabel ini untuk memeriksa apakah rumus, tanggal jenjang, dan penilaian sudah benar.',
                    'Rumus Kinetik sudah diuji terhadap rekapan kantor dan hasilnya sama. Catatan pengujiannya ada di docs/kinetik/ak-validation-2026-11-04.md.',
                ],
                tip: 'AK resmi memakai satu predikat tahunan, sedangkan Kinetik memakai predikat bulanan. Selisih kecil untuk tahun yang sudah lewat itu wajar.',
            },
            {
                title: 'Angka Kredit dari PAK',
                where: 'Menu Rekap Tim → Angka Kredit Tim',
                route: 'credit.team',
                body: [
                    'Golongan dan predikat SKP ditarik dari kipApp setiap Senin pagi, atau saat admin menekan Sinkronkan pada Sinkronisasi Angka Kredit di halaman Integrasi kipApp. Tanpa PAK, AK dihitung dari SKP sejak golongan atau jenjang saat ini.',
                    'Pegawai bisa mengisi AK dari PAK terakhirnya sendiri di Angka Kredit Saya. Baris itu bertanda "PAK diisi pegawai, belum dicek".',
                    'Cocokkan dengan dokumen PAK, lalu klik ikon pensil di baris pegawai dan simpan nilainya. Tandanya berubah menjadi "PAK dicek admin". AK sesudah tanggal PAK ditambahkan dari predikat kipApp.',
                    'Kosongkan kedua kolom untuk kembali ke estimasi.',
                    'Saat pegawai naik pangkat, Kinetik memulai hitungan dari 0 sejak bulan kenaikan dan mengabaikan AK dasar yang tanggalnya sebelum itu. Aturan ini bisa dimatikan lewat konfigurasi kinetik.credit.restart_on_promotion.',
                ],
            },
        ],
    },
];

export type StatusTone = 'gray' | 'blue' | 'amber' | 'green' | 'emerald' | 'red';

export interface GuideStatusStage {
    label: string;
    tone: StatusTone;
    /** What the status means, in plain words. */
    meaning: string;
    /** What the reader does, or what happens next. */
    next: string;
}

export interface GuideStatusFlow {
    id: string;
    title: string;
    /** Who sees these statuses, and where. */
    where: string;
    intro: string;
    /** The statuses in the order they normally happen. They are drawn as a chain. */
    stages: GuideStatusStage[];
    /** Statuses off the main chain, or rules that change the chain. */
    sides?: GuideStatusStage[];
    /** True when the stages are separate states instead of a chain (colour scales). */
    scale?: boolean;
    note?: string;
}

/** Every status a person can see in Kinetik, with what it means and what happens next. */
export const GUIDE_STATUS: GuideStatusFlow[] = [
    {
        id: 'kegiatan',
        title: 'Kegiatan Anda',
        where: 'Anggota · Rekap Mingguan, detail kegiatan',
        intro: 'Satu kegiatan melewati lima status. Status berganti sendiri saat Anda atau Kinetik melakukan langkah berikutnya.',
        stages: [
            { label: 'Direncanakan', tone: 'gray', meaning: 'Kegiatan ada di Kinetik, belum ada di kipApp.', next: 'Setelah progres 100, capaian, dan bukti dukung terisi, dan tanggal mulainya tiba, Kinetik membuatnya di kipApp otomatis, dimulai pukul 05.10 WITA dan diulang tiap jam kerja. Atau tekan "Kirim ke kipApp". Kegiatan hasil impor Excel tidak dikirim otomatis: Anda yang menekan tombolnya.' },
            { label: 'Ada di kipApp', tone: 'blue', meaning: 'Kegiatan sudah dibuat di kipApp dengan progres 0.', next: 'Kerjakan, lalu isi form Realisasi (capaian, progres, bukti, uraian).' },
            { label: 'Berjalan', tone: 'amber', meaning: 'Progres sudah di atas 0 tetapi belum 100.', next: 'Lanjutkan mengisi Realisasi. Saat selesai, tekan "Tandai selesai".' },
            { label: 'Selesai, belum dikirim', tone: 'green', meaning: 'Progres 100 dan uraian sudah ada, tetapi belum dikirim ke atasan.', next: 'Dikirim ke atasan sekali per triwulan lewat menu Kirim ke Atasan, setelah dicek kelengkapannya.' },
            { label: 'Terkirim ke atasan', tone: 'emerald', meaning: 'Atasan sudah menerima kegiatan untuk dinilai.', next: 'Kegiatan tidak bisa diubah atau dihapus. Hanya "Masukan ke capaian SKP" yang boleh diubah. Salah kirim? Tekan "Batalkan kirim".' },
        ],
        sides: [
            { label: 'Belum terkirim ke kipApp: (alasan)', tone: 'red', meaning: 'Pengiriman otomatis gagal, misalnya SKP Tahunan belum Ditetapkan atau sesi kipApp berakhir. Alasannya tertulis di detail kegiatan.', next: 'Perbaiki penyebabnya. Kinetik mencoba lagi tiap jam sampai tanggal selesai dan memberi tahu Anda satu kali lewat lonceng.' },
            { label: 'Dibatalkan', tone: 'gray', meaning: 'Rencana yang belum dikirim ke kipApp dibatalkan atau dihapus.', next: 'Tidak dihitung di rekap. Kegiatan yang sudah ada di kipApp dihapus lewat "Hapus kegiatan", dan Kinetik menghapusnya juga di kipApp.' },
            { label: 'Terkunci (ada keterangan)', tone: 'amber', meaning: 'SKP triwulan kegiatan itu sudah meninggalkan "Sedang dibuat" di kipApp, jadi kipApp tidak menerima perubahan.', next: 'Minta atasan mengembalikan SKP ke "Sedang dibuat", atau ubah langsung di kipApp.' },
        ],
    },
    {
        id: 'daftar-saya',
        title: 'Daftar bulan dan minggu Anda',
        where: 'Anggota · Rekap Mingguan, daftar bulan dan daftar minggu',
        intro: 'Tiap baris adalah satu bulan atau satu minggu. Statusnya dihitung dari kegiatan Anda pada periode itu: berapa yang capaiannya sudah terisi dan berapa yang sudah terkirim ke atasan.',
        stages: [
            { label: 'Belum ada kegiatan', tone: 'gray', meaning: 'Anda belum punya kegiatan pada periode itu.', next: 'Tekan "Tambah" bila ada yang perlu dicatat atau direncanakan.' },
            { label: 'Belum ditulis', tone: 'amber', meaning: 'Ada kegiatan, tetapi belum ada satu pun capaian yang terisi.', next: 'Buka kegiatan, isi form Realisasi.' },
            { label: 'Sedang ditulis', tone: 'blue', meaning: 'Sebagian kegiatan sudah ada capaiannya, dan belum semuanya terkirim ke atasan.', next: 'Lengkapi capaian kegiatan yang tersisa, lalu kirim ke atasan.' },
            { label: 'Terkirim ke atasan', tone: 'emerald', meaning: 'Semua kegiatan yang capaiannya terisi sudah terkirim ke atasan.', next: 'Selesai untuk periode itu.' },
        ],
    },
    {
        id: 'skp',
        title: 'SKP Anda',
        where: 'Anggota, PJ dan pimpinan · SKP Saya dan Persetujuan SKP',
        intro: 'SKP Tahunan dan SKP triwulan punya jalur sendiri. Keduanya dimulai dari "Sedang dibuat".',
        stages: [
            { label: 'Sedang dibuat', tone: 'gray', meaning: 'SKP masih milik pegawai dan bisa diubah.', next: 'Susun RK, tambah IKI, lalu tekan Kirim.' },
            { label: 'Menunggu ditetapkan atasan  /  Diperiksa atasan (di kipApp: Sedang diperiksa)', tone: 'amber', meaning: 'SKP Tahunan menunggu atasan menetapkannya. SKP triwulan sedang diperiksa atasan untuk dinilai.', next: 'Atasan membukanya di Persetujuan SKP. Pegawai bisa "Batalkan pengiriman" selama belum diproses.' },
            { label: 'Ditetapkan  /  Dinilai', tone: 'emerald', meaning: 'SKP Tahunan sudah ditetapkan. SKP triwulan sudah dinilai.', next: 'Selesai. Nilai SKP triwulan ikut ke Angka Kredit.' },
        ],
        sides: [
            { label: 'Dikembalikan', tone: 'red', meaning: 'Atasan mengembalikan SKP ke pegawai.', next: 'SKP kembali ke "Sedang dibuat". Pegawai memperbaiki lalu mengirim lagi.' },
            { label: 'SKP TW belum ada', tone: 'gray', meaning: 'Awal triwulan baru belum ada SKP triwulannya di kipApp.', next: 'Kinetik membuatnya otomatis dari SKP Tahunan saat kegiatan pertama triwulan itu dikirim. "Sinkronkan data saya" memberi peringatan bila SKP itu belum ada.' },
        ],
    },
    {
        id: 'rekap',
        title: 'Rekap mingguan dan bulanan tim',
        where: 'PJ · Rekap Tim → Mingguan dan Bulanan (daftar minggu atau bulan)',
        intro: 'Status dihitung dari RK yang punya kegiatan pada minggu atau bulan itu, dan apakah PJ sudah menulis parafrasenya. Tidak ada kunci: parafrase boleh diubah kapan saja.',
        stages: [
            { label: 'Belum ada kegiatan', tone: 'gray', meaning: 'Belum ada anggota yang punya kegiatan pada periode itu.', next: 'Tidak ada yang perlu ditulis.' },
            { label: 'Belum ditulis', tone: 'amber', meaning: 'Ada kegiatan, tetapi belum ada satu pun parafrase RK.', next: 'Buka periodenya, pilih RK, tekan "Isi parafrase".' },
            { label: 'Sedang ditulis', tone: 'blue', meaning: 'Sebagian RK yang punya kegiatan sudah ada parafrasenya, sebagian belum.', next: 'Lanjutkan RK yang masih "Belum ditulis". Kolom "RK sudah ditulis" dibanding "RK dengan kegiatan" menunjukkan sisanya.' },
            { label: 'Selesai ditulis', tone: 'emerald', meaning: 'Semua RK yang punya kegiatan sudah ada parafrasenya.', next: 'Siap untuk rapat. Tambahkan bukti rapat bila ada.' },
        ],
        note: 'Hanya PJ, PIC RK, dan admin yang bisa membaca teks parafrase. Anggota dan pimpinan hanya melihat angka dan statusnya.',
    },
    {
        id: 'ringkasan',
        title: 'Ringkasan Rapat',
        where: 'Pimpinan · Rekap Tim → Ringkasan Rapat. PJ · Beranda',
        intro: 'Satu tabel per bulan: tiap tim, tiap minggu, dan rapat bulanan. Isinya status rekap dan jumlah bukti rapat.',
        scale: true,
        stages: [
            { label: 'Lengkap', tone: 'emerald', meaning: 'Semua RK yang punya kegiatan pada periode itu sudah ada parafrasenya.', next: 'Tidak perlu ditindaklanjuti.' },
            { label: 'Sebagian', tone: 'amber', meaning: 'Baru sebagian RK yang ada parafrasenya.', next: 'Klik untuk membuka rekap timnya.' },
            { label: 'Belum', tone: 'red', meaning: 'Minggu atau bulan itu sudah lewat dan belum ada parafrase.', next: 'Tanyakan ke PJ tim.' },
            { label: '—', tone: 'gray', meaning: 'Minggu atau bulannya belum tiba.', next: 'Tidak dihitung.' },
        ],
        note: 'Angka kecil dengan ikon dokumen di sebelah status adalah jumlah bukti rapat (notula, foto, daftar hadir) yang diunggah PJ.',
    },
    {
        id: 'triwulan',
        title: 'Rekap triwulan (FRA)',
        where: 'PJ · Rekap Tim → Triwulanan (FRA)',
        intro: 'Rekap triwulan dikonfirmasi baris demi baris, lalu dikunci setelah rapat. Kunci ini hanya ada di triwulan.',
        stages: [
            { label: 'Belum', tone: 'amber', meaning: 'Baris RK belum dikonfirmasi PJ.', next: 'Periksa angkanya, isi kendala, solusi dan RTL, lalu konfirmasi. "Konfirmasi semua capaian 100%" mengonfirmasi sekaligus semua baris yang sudah 100%.' },
            { label: 'Terkonfirmasi', tone: 'green', meaning: 'PJ sudah memeriksa baris itu.', next: 'Setelah semua baris terkonfirmasi, kunci triwulan dari bagian atas halaman.' },
            { label: 'Rekap dikunci', tone: 'emerald', meaning: 'Triwulan ditutup. Isinya tidak berubah sampai kunci dibuka.', next: 'RTL pada rekap yang dikunci menjadi rencana kegiatan di triwulan berikutnya.' },
        ],
    },
    {
        id: 'capaian',
        title: 'Capaian tim',
        where: 'Pimpinan · Review Bersama',
        intro: 'Warna capaian rata-rata satu tim pada periode yang dipilih.',
        scale: true,
        stages: [
            { label: 'Tercapai', tone: 'emerald', meaning: 'Capaian 100% atau lebih.', next: '—' },
            { label: 'Progres', tone: 'amber', meaning: 'Capaian 70% sampai di bawah 100%.', next: '—' },
            { label: 'Rendah', tone: 'red', meaning: 'Capaian di bawah 70%.', next: 'Buka baris timnya untuk melihat projek dan RK penyebabnya.' },
            { label: 'Belum ada data', tone: 'gray', meaning: 'Belum ada kegiatan dengan target dan capaian. Ini bukan capaian 0%.', next: 'Minta tim mengisi target dan capaian.' },
        ],
    },
    {
        id: 'rencana',
        title: 'Kepatuhan rencana dan hasil rencana',
        where: 'Pimpinan dan PJ · Kepatuhan Rencana',
        intro: 'Dua hal berbeda. Warna sel menunjukkan berapa anggota yang membuat rencana. Hasil rencana menunjukkan apa jadinya rencana minggu yang sudah lewat.',
        scale: true,
        stages: [
            { label: 'Hijau (80% atau lebih)', tone: 'emerald', meaning: 'Hampir semua anggota sudah membuat rencana minggu itu.', next: 'Batas membuat rencana: Senin pukul 09.00.' },
            { label: 'Kuning (50% sampai 79%)', tone: 'amber', meaning: 'Sebagian anggota belum membuat rencana.', next: 'Nama yang belum tampil di halaman yang sama.' },
            { label: 'Merah (di bawah 50%)', tone: 'red', meaning: 'Banyak anggota belum membuat rencana.', next: 'Ingatkan lewat PJ.' },
            { label: 'Selesai / Berjalan / Belum mulai', tone: 'blue', meaning: 'Hasil rencana minggu lalu. Selesai: semua kegiatan kipApp untuk RK itu pada tanggalnya progres 100. Berjalan: ada kegiatan tetapi belum semua 100. Belum mulai: tidak ada kegiatan kipApp untuk rencana itu.', next: 'PJ bisa mengubah hasil dengan alasan tertulis bila hitungan otomatis tidak tepat.' },
        ],
    },
    {
        id: 'beranda',
        title: 'Kartu di Beranda',
        where: 'Anggota dan pimpinan · Beranda',
        intro: 'Setiap kartu punya satu tanda di pojok kanan atas. Tanda itu dihitung dari yang tertinggal pada kartu itu.',
        scale: true,
        stages: [
            { label: 'Aman', tone: 'emerald', meaning: 'Tidak ada yang tertinggal.', next: 'Tidak perlu tindakan.' },
            { label: 'Perlu dicek', tone: 'amber', meaning: 'Ada yang tertinggal, tetapi separuh atau lebih sudah beres.', next: 'Nama atau kegiatan yang tertinggal tertulis di kartu.' },
            { label: 'Perlu tindakan', tone: 'red', meaning: 'Kurang dari separuh yang beres.', next: 'Buka halaman lewat tautan di bawah kartu.' },
        ],
    },
    {
        id: 'fra',
        title: 'Warna Kertas Kerja FRA',
        where: 'PJ, pimpinan dan admin · Kertas Kerja FRA',
        intro: 'Warna persen tiap bulan dan angka kumulatif dibanding target tahunan indikator.',
        scale: true,
        stages: [
            { label: 'Hijau', tone: 'emerald', meaning: 'Target tahunan tercapai atau terlampaui.', next: '—' },
            { label: 'Kuning', tone: 'amber', meaning: 'Ada hasil tetapi belum mencapai target.', next: '—' },
            { label: 'Merah', tone: 'red', meaning: 'Kosong atau nol pada bulan yang sudah lewat.', next: 'PJ tim pemilik mengisi X dan Y bulan itu.' },
            { label: 'Abu-abu', tone: 'gray', meaning: 'Bulan yang belum tiba.', next: '—' },
        ],
    },
];

export const GUIDE_FAQ = [
    {
        q: 'Tim kami tidak muncul di Progres per IKU, atau angkanya kosong. Apa yang harus dilakukan?',
        a: 'Kegiatan hanya masuk angka bila punya target dan capaian. Kegiatan yang datang dari kipApp belum punya target (kipApp tidak menyimpannya), jadi anggota perlu mengisinya sekali: buka "Isi Capaian Bulanan", pilih bulannya, isi target dan capaian semua kegiatan, lalu simpan. Pada tanggal 2 tiap bulan Kinetik mengirim pengingat ke anggota yang kegiatan bulan lalunya belum lengkap, dan satu ringkasan ke PJ tim. Tab "Per tim" di Progres per IKU menunjukkan siapa yang belum mengisi.',
    },
    {
        q: 'Admin mengimpor Excel, padahal saya sudah mengisi kegiatan yang sama di kipApp. Apakah jadi dobel?',
        a: 'Tidak seharusnya. Saat impor, baris yang mirip dengan kegiatan Anda yang sudah ada tidak dicentang. Bila tetap ada kegiatan dari impor yang sama dengan yang di kipApp, sinkronisasi menggabungkannya (satu kegiatan, bukan dua). Kegiatan hasil impor juga ditahan sebelum dikirim ke kipApp bila ada yang mirip di sana: Anda melihat "Mirip dengan kegiatan yang sudah ada di kipApp" dan dua pilihan, hapus kegiatannya atau tekan "Kirim tetap" bila memang berbeda.',
    },
    {
        q: 'Di form kegiatan ada projek yang bukan pekerjaan saya, dan RK-nya kosong.',
        a: 'Daftar Projek hanya memuat projek yang Anda ikuti sebagai anggota atau ketua, projek tempat RK Anda berada, dan semua projek tim yang Anda pimpin. Projek lain di tim yang sama tidak lagi tampil. Bila projek Anda belum ada di daftar, tekan "Sinkronkan data saya" di Rekap Mingguan, atau minta admin menjalankan Sinkronisasi Struktur supaya keanggotaan projek diperbarui.',
    },
    {
        q: 'Kinetik menolak capaian saya karena "melebihi target". Apa yang harus dilakukan?',
        a: 'Capaian tidak boleh lebih besar dari target, supaya persentase capaian paling tinggi 100%. Bila pekerjaannya memang lebih banyak dari rencana, naikkan target di kegiatan itu lebih dulu (misalnya dari 38 menjadi 39), lalu isi capaiannya. Aturan ini berlaku di form Realisasi, Isi Capaian Bulanan, rekap RK oleh PJ, dan impor Excel.',
    },
    {
        q: 'Mengapa angka FRA tidak terisi otomatis dari kegiatan harian?',
        a: 'IKI dan kegiatan harian di SKP adalah ukuran kinerja pegawai, sedangkan FRA mengukur IKU, indikator kinerja utama organisasi. Walau IKI turunan dari IKU, capaian IKI tidak bisa dijumlahkan jadi capaian IKU. Pertama, tidak semua IKI dikerjakan dengan baik oleh pegawai. Kedua, tidak semua pekerjaan langsung menjadi IKU, karena tiap IKU punya data dasar dan rumus hitungnya sendiri. Karena itu X dan Y di Kertas Kerja FRA tetap diisi PJ tim pemilik indikator tiap bulan. Kinetik menghitung persen, warna, dan file Excelnya.',
    },
    {
        q: 'Pekerjaan saya ada di tim pusat (bukan tim BPS Sulteng). RK dan projeknya tidak muncul di form kegiatan.',
        a: 'Kinetik hanya menyimpan tim BPS Sulteng, tetapi RK di SKP Anda tetap terbaca. Tekan "Sinkronkan data saya" di Rekap Mingguan: RK dari tim pusat muncul di daftar RK, dan projek tidak wajib dipilih. Projek pusatnya muncul di pilihan Projek dengan keterangan timnya, misalnya "Kegiatan Penilaian Potensi dan Kompetensi sesuai kebutuhan organisasi (12. Asesmen dan Konseling)". Pilih projek itu, lalu RK-nya tampil. Nama projek juga tertulis di belakang RK, sehingga Anda bisa mencari dari kolom RK. Kegiatannya tetap dikirim ke RK yang sama di kipApp.',
    },
    {
        q: 'Bulan Oktober sudah lewat. Bagaimana mengisi target dan capaiannya tanpa mengisi dua kali?',
        a: 'Buka menu "Isi Capaian Bulanan", pilih bulannya, lalu isi target dan capaian semua kegiatan sekaligus. Untuk kegiatan yang sudah ada di kipApp, Kinetik memperbarui kipApp dalam simpanan yang sama. Kegiatan yang terkunci atau uraiannya kurang dari 10 karakter tetap tersimpan di Kinetik dan diberi peringatan.',
    },
    {
        q: 'Saya punya pekerjaan yang tidak ada di RK tim. Di mana mencatatnya?',
        a: 'Buat RK tambahan dari form kegiatan (tautan "Tambah RK tambahan"). RK itu hanya milik Anda. Pengiriman RK tambahan ke kipApp baru jalan setelah admin menyalakan saklarnya; sebelum itu kegiatan tetap aman di Kinetik.',
    },
    {
        q: 'Angka Kredit saya kembali ke 0 setelah naik pangkat. Apakah salah?',
        a: 'Tidak. Setiap kenaikan pangkat memulai hitungan AK dari awal, jadi Kinetik menghitung dari bulan kenaikan. Bila Anda punya AK dasar dari PAK dengan tanggal sebelum kenaikan, angka itu tidak dipakai lagi. Halaman Angka Kredit Saya mencatat tanggal mulai hitungannya.',
    },
    {
        q: 'Sebagai ketua tim, RK mana yang saya pilih untuk kegiatan saya? Daftar RK-nya kosong.',
        a: 'Kegiatan ketua tim menempel ke RK di SKP ketua sendiri, yaitu RK Ketua: RK yang menjadi atasan projek-projek tim. RK Ketua tidak berada di dalam projek, jadi projek tidak perlu dipilih. Kosongkan kolom Projek (atau pilih "Tanpa projek"), lalu pilih RK Anda dari daftar. RK anggota hanya dipakai anggota tim.',
    },
    {
        q: 'Mengapa satu RK tampil berkali-kali di daftar RK?',
        a: 'kipApp menyalin RK untuk setiap anggota dan setiap SKP triwulan, jadi teks RK yang sama tersimpan beberapa kali. Kinetik menampilkannya sekali per projek (atau per tim bila tanpa projek) dan memilih salinan milik tim Anda. Jika Anda masih melihat RK kembar, laporkan lewat tombol "Laporkan masalah" beserta nama RK-nya.',
    },
    {
        q: 'Mengapa target kegiatan saya tampil "-"?',
        a: 'kipApp tidak menyimpan target untuk kegiatan. Target adalah isian Kinetik, jadi kegiatan yang masuk dari kipApp (bukan dibuat di Kinetik) belum punya target dan menampilkan "-". Ini bukan kesalahan. Isi target dan satuannya saat mengubah kegiatan bila Anda ingin rekap menghitungnya.',
    },
    {
        q: 'Bagaimana menyimpan riwayat kerja saya bila kipApp ditinggalkan?',
        a: 'Buka menu "Arsip kipApp", pilih tahun, lalu tekan "Unduh Excel". File berisi dua lembar: Kegiatan (tim, projek, RK, uraian, tanggal, target, progres, uraian capaian, bukti dukung, ID kipApp) dan RK. Admin bisa mengunduh arsip pegawai mana pun. Kinetik belum mengirim data ke e-Kinerja BKN secara otomatis, karena BKN belum membuka akses resmi untuk aplikasi lain. File ini dipakai untuk mengisi atau memeriksa e-Kinerja secara manual.',
    },
    {
        q: 'Mengapa kegiatan saya tidak terkirim ke kipApp dan muncul pesan "RK ini belum punya projek"?',
        a: 'RK itu seharusnya berada di sebuah projek, tetapi Kinetik belum tahu projek yang mana. Buka kegiatannya dan pilih projek, atau minta admin menjalankan Sinkronisasi Struktur. Setelah projeknya jelas, kegiatan terkirim pada percobaan berikutnya.',
    },
    {
        q: 'Ada yang tidak berjalan. Bagaimana melaporkannya ke admin?',
        a: 'Tekan "Laporkan masalah" di bagian atas halaman tempat masalahnya muncul. Tulis ringkasan dan apa yang Anda lakukan. Laporan sampai ke admin bersama alamat halamannya, jadi tidak perlu mengirim tangkapan layar terpisah, tetapi sebutkan pesan yang muncul.',
    },
    {
        q: 'Mengapa Kinetik meminta kata sandi SSO saya?',
        a: 'Agar Kinetik dapat mengambil dan, nanti, mengirim kegiatan ke kipApp atas nama Anda tanpa Anda membuka kipApp. Kata sandi disimpan terenkripsi, tidak pernah ditampilkan kembali, dan Kinetik berhenti mencoba bila kata sandi ditolak satu kali.',
    },
    {
        q: 'Login ditolak padahal kata sandi SSO saya benar.',
        a: 'Masuk dengan username SSO (misalnya "nama") atau "nama@bps.go.id", bukan email lain. Jika baru mengganti kata sandi SSO, pakai yang baru. Jika masih gagal, pastikan akun SSO Anda dapat membuka kipApp, lalu laporkan lewat tombol "Laporkan masalah" atau hubungi admin. Kinetik memakai username SSO yang sama dengan bagian email sebelum @.',
    },
    {
        q: 'Bagaimana memilih dari daftar yang panjang?',
        a: 'Semua daftar pilihan di Kinetik memiliki kolom "Cari…". Ketik sebagian nama, lalu pilih dengan klik atau tombol panah dan Enter.',
    },
    {
        q: 'Rencana Kinerja pada kegiatan saya kosong. Apa yang harus dilakukan?',
        a: 'Tekan "Sinkronkan data saya" di Rekap Mingguan untuk menarik RK Anda dari kipApp. Jika RK Anda masih tidak ada di daftar, minta admin menjalankan Sinkronisasi Struktur.',
    },
    {
        q: 'Apa arti "Sedang ditulis" di daftar minggu atau bulan?',
        a: 'Sebagian RK yang punya kegiatan sudah ada parafrasenya, sebagian belum. Lanjutkan RK yang masih "Belum ditulis" sampai statusnya menjadi "Selesai ditulis". Semua status ada di bagian "Arti status" di Buku Pedoman ini.',
    },
    {
        q: 'Apa arti "Belum ada data"?',
        a: 'Belum ada kegiatan dengan target dan capaian pada periode itu. Ini berbeda dengan capaian 0%.',
    },
    {
        q: 'Kegiatan bulan lalu yang sudah saya kirim di kipApp tidak muncul.',
        a: 'Minta admin menjalankan Sinkronisasi Struktur, lalu Sinkronisasi Kegiatan. Sinkronisasi menarik semua SKP triwulanan dalam periode, termasuk kegiatan yang sudah dikirim.',
    },
    {
        q: 'Kegiatan saya belum muncul di Rekap Mingguan.',
        a: 'Kinetik menarik kegiatan kipApp setiap pagi. Kegiatan yang baru diinput hari ini muncul besok, atau setelah admin menyinkronkan.',
    },
];
