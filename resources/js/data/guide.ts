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
    anggotaKlaim: {
        src: '/images/pedoman/anggota-klaim.webp',
        alt: 'Halaman Rekap Mingguan dengan satu kegiatan yang siap diklaim',
        width: 1024, height: 814,
        markers: [
            { n: 1, x: 2.6, y: 15.8, label: 'Progres minggu ini: berapa kegiatan sudah disimpan.' },
            { n: 2, x: 65.3, y: 16.8, label: 'Isi cepat semua kegiatan yang belum diklaim.' },
            { n: 3, x: 81.1, y: 16.8, label: 'Simpan semua kegiatan yang sudah lengkap sekaligus.' },
            { n: 4, x: 2.6, y: 55.4, label: 'Pilih Projek tempat kegiatan dikerjakan.' },
            { n: 5, x: 2.6, y: 67.5, label: 'Target, Realisasi dan Satuan. Capaian dihitung otomatis.' },
            { n: 6, x: 2.6, y: 81.2, label: 'Kendala wajib diisi. Tulis "-" jika tidak ada.' },
            { n: 7, x: 2.6, y: 94.6, label: 'Isi cepat untuk satu kegiatan ini saja.' },
        ],
    },
    pjRekapTim: {
        src: '/images/pedoman/pj-rekap-tim.webp',
        alt: 'Halaman Rekap Tim mingguan: kartu per projek dengan satu baris per kegiatan, baris gabungan, Laporan Tersimpan dan tombol Kunci rekap di bawah',
        width: 1024, height: 2071,
        markers: [
            { n: 1, x: 82.5, y: 17.1, label: 'Centang baris satu projek, lalu klik Gabungkan yang dipilih untuk menjadikannya satu baris.' },
            { n: 2, x: 5.2, y: 23, label: 'Parafrase PJ, lalu Permasalahan, Solusi dan RTL. Isi dari anggota sudah ada, jadi cukup dirapikan.' },
            { n: 3, x: 90.2, y: 39.1, label: 'Simpan: seluruh baris projek ini masuk ke Laporan Tersimpan.' },
            { n: 4, x: 8.7, y: 51.6, label: 'Baris gabungan: target dan realisasi adalah total semua kegiatan yang digabung.' },
            { n: 5, x: 3.5, y: 64.7, label: 'Laporan Tersimpan: hasil rekap minggu ini per projek, siap dibacakan di rapat.' },
            { n: 6, x: 3.5, y: 73.8, label: 'Bukti rapat: Notula, Dokumentasi, Daftar Hadir.' },
            { n: 7, x: 5.2, y: 95.4, label: 'Kunci rekap setelah rapat selesai, supaya klaim dan isi laporan tidak berubah lagi.' },
            { n: 8, x: 84, y: 5.9, label: 'Unduh Excel format Rapat Mingguan.' },
        ],
    },
    pjRekapBulanan: {
        src: '/images/pedoman/pj-rekap-bulanan.webp',
        alt: 'Halaman Rekap Tim bulanan: rekap mingguan yang dapat dibuka, Ringkasan Bulanan per projek dan Rincian per RK yang terlipat',
        width: 1024, height: 1417,
        markers: [
            { n: 1, x: 20.8, y: 8.6, label: 'Isi Uraian, Permasalahan, Solusi dan RTL dari rekap mingguan (hanya kolom kosong).' },
            { n: 2, x: 37.5, y: 8.6, label: 'Konfirmasi semua baris yang capaiannya 100%.' },
            { n: 3, x: 5.2, y: 14, label: 'Rekap setiap minggu bulan ini. Buka satu minggu untuk membacanya.' },
            { n: 4, x: 84.2, y: 33.1, label: 'Pre-fill: menyusun draf ringkasan bulanan dari rekap mingguan.' },
            { n: 5, x: 5.2, y: 45.6, label: 'Satu entri per projek: Uraian, Permasalahan, Solusi dan RTL.' },
            { n: 6, x: 90.1, y: 57.3, label: 'Simpan entri projek ini.' },
            { n: 7, x: 3.5, y: 87.9, label: 'Rincian per RK untuk konfirmasi, Excel dan FRA. Klik untuk membuka.' },
            { n: 8, x: 87.6, y: 93.3, label: 'Kunci rekap bulan ini setelah rapat.' },
        ],
    },
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
    pimpinanReviewBersama: {
        src: '/images/pedoman/pimpinan-review-bersama.webp',
        alt: 'Halaman Review Bersama untuk pimpinan dengan status capaian dan Catatan Pimpinan',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 5.2, y: 7.2, label: 'Pilih Mingguan, Bulanan atau Triwulanan.' },
            { n: 2, x: 40.2, y: 7.2, label: 'Pindah periode dengan ‹ dan ›.' },
            { n: 3, x: 3.5, y: 17.8, label: 'Ringkasan kantor: tim, capaian, rekap dikunci, baris dikonfirmasi.' },
            { n: 4, x: 3.5, y: 26.3, label: 'Saring satu tim.' },
            { n: 5, x: 32.4, y: 26.3, label: 'Saring per status: Tercapai, Progres, Rendah.' },
            { n: 6, x: 67.5, y: 39.1, label: 'Status capaian tim dan status kunci PJ.' },
            { n: 7, x: 76.1, y: 39.1, label: 'Catatan Pimpinan untuk tim pada periode ini.' },
            { n: 8, x: 33.6, y: 57, label: 'Projek tim beserta PIC, capaian dan catatannya.' },
            { n: 9, x: 77.3, y: 7.2, label: 'Unduh Excel semua tim untuk rapat.' },
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
            { n: 5, x: 38.5, y: 7.3, label: 'Sinkronisasi Kegiatan: kegiatan harian semua pegawai.' },
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

export const GUIDE_OVERVIEW = [
    { who: 'Anggota', what: 'Mengisi kegiatan harian di kipApp, lalu mengklaim kegiatan mingguan di Kinetik.' },
    { who: 'PJ / Ketua Tim', what: 'Meninjau rekap tim, mengisi solusi dan rencana tindak lanjut, menambah bukti rapat, lalu mengunci rekap sebelum rapat.' },
    { who: 'Pimpinan', what: 'Membaca rekap semua tim di Review Bersama, menulis Catatan Pimpinan, dan mengunduh Excel untuk rapat.' },
];

export const GUIDE_FLOWS: GuideFlow[] = [
    {
        role: 'anggota',
        label: 'Anggota',
        summary: 'Setiap minggu Anda mengklaim kegiatan kipApp ke Rencana Kinerja (RK) dan Projek, lalu mengisi capaian dan kendala.',
        replaces: 'Sheet "Kegiatan Mingguan Anggota"',
        steps: [
            {
                title: 'Lihat Rencana Minggu Ini setiap Senin',
                where: 'Menu Kegiatan → Rencana Minggu Ini',
                route: 'weekly-plan.index',
                body: [
                    'Kartu Anda tampil paling atas. Isinya fokus dari PJ untuk minggu ini, RK yang belum punya kegiatan di triwulan ini, dan kegiatan yang progresnya belum 100%.',
                    'Mulai minggu dari daftar itu: kerjakan fokus PJ, lalu lanjutkan kegiatan yang belum selesai.',
                ],
            },
            {
                title: 'Buat rencana kerja minggu ini',
                where: 'Menu Kegiatan → Rencana Minggu Ini',
                route: 'weekly-plan.index',
                body: [
                    'Pada kartu Anda, klik "Tambah rencana". Pilih RK, tulis apa yang akan dikerjakan, lalu isi tanggal mulai dan selesai.',
                    'Pilih Projek bila RK itu belum punya projek. Target dan satuan boleh dikosongkan.',
                    'Isi rencana paling lambat Senin pukul 09.00. Anda dapat mengubah atau membatalkan rencana selama belum dikirim ke kipApp.',
                ],
            },
            {
                title: 'Isi kegiatan harian di kipApp',
                body: [
                    'Catat setiap kegiatan harian di kipApp seperti biasa, lengkap dengan uraian dan link bukti dukung.',
                    'Kinetik menarik kegiatan kipApp setiap pagi pukul 05.00 WITA. Kegiatan yang Anda isi sesudahnya muncul besok pagi, atau lebih cepat bila admin menjalankan Sinkronisasi Kegiatan.',
                ],
            },
            {
                title: 'Buka Rekap Mingguan',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Halaman terbuka pada minggu kegiatan terakhir Anda. Gunakan tombol ‹ dan › untuk pindah minggu.',
                    'Kotak di atas menunjukkan berapa kegiatan yang sudah disimpan dan berapa yang masih menunggu.',
                ],
            },
            {
                title: 'Klaim setiap kegiatan',
                body: [
                    'Rencana Kinerja terisi otomatis dari kipApp.',
                    'Pilih Projek tempat kegiatan ini dikerjakan. kipApp tidak menyimpan projek sebuah RK, jadi Kinetik mengisinya lebih dulu: projek yang Anda pilih terakhir untuk RK yang sama, projek yang namanya tertulis di RK, atau satu-satunya projek Anda di tim itu. Periksa lalu ubah jika perlu.',
                    'Isi Target, Realisasi dan Satuan (wajib). Capaian dihitung otomatis. Projek juga wajib, kecuali RK Ketua Anda di kipApp tidak punya projek.',
                    '"Isi cepat" mengisi kolom yang masih kosong: target 1, realisasi dari progres kegiatan di kipApp, satuan dari IKI RK (atau Kegiatan), kendala "-". Periksa lalu ubah jika perlu.',
                    'Kegiatan yang berlangsung lebih dari seminggu, misalnya kegiatan bulanan atau triwulanan, bertanda "Klaim tiap minggu". Kegiatan itu muncul di setiap minggu yang dilaluinya. Klaim target dan realisasi minggu itu saja, lalu ulangi di minggu berikutnya.',
                    'Isi Kendala. Tulis "-" jika tidak ada kendala. Solusi dan Rencana Tindak Lanjut diisi PJ saat rapat tim.',
                    'Jam Mulai dan Jam Selesai boleh dikosongkan.',
                ],
                shot: SHOTS.anggotaKlaim,
                tip: 'Tombol "Isi cepat" mengisi kolom yang masih kosong dengan target 1, realisasi 1, satuan Kegiatan dan kendala "-". Kolom yang sudah Anda isi tidak diubah.',
            },
            {
                title: 'Simpan',
                body: [
                    'Tekan "Simpan ke Rekap" untuk satu kegiatan, atau "Simpan semua" untuk menyimpan semua kegiatan yang sudah lengkap sekaligus.',
                    'Kegiatan yang tersimpan diberi tanda "Tersimpan" dan formnya ditutup. Tekan "Ubah" untuk memperbaiki.',
                ],
            },
            {
                title: 'Perhatikan tanda "Dikunci PJ"',
                body: [
                    'Setelah PJ mengunci rekap untuk rapat, kegiatan di periode itu tidak dapat diubah.',
                    'Jika perlu koreksi, minta PJ membuka kunci terlebih dahulu.',
                ],
            },
            {
                title: 'Lihat hasilnya',
                where: 'Menu Kegiatan → Kegiatan Saya, dan menu Rekap Tim',
                route: 'kip-activities.index',
                body: [
                    'Kegiatan Saya menampilkan semua kegiatan kipApp Anda dan status klaimnya. Saring per Minggu, Bulan atau Triwulan, lalu pindah periode dengan ‹ dan ›.',
                    'Rekap Tim menampilkan rekap tim Anda. Anda dapat membaca, tetapi hanya PJ yang mengubah ringkasan.',
                ],
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
                ],
                shot: SHOTS.anggotaAngkaKredit,
                tip: 'Angka ini adalah pemantauan. Usulan kenaikan pangkat tetap memakai PAK resmi.',
            },
        ],
    },
    {
        role: 'pj',
        label: 'PJ / Ketua Tim',
        summary: 'Anda menyiapkan bahan rapat tim dan rapat dengan pimpinan dari klaim anggota, lalu mengunci rekap.',
        replaces: 'Sheet "Rapat Mingguan" dan "Rapat Bulanan"',
        steps: [
            {
                title: 'Klaim kegiatan Anda sendiri',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: ['Sama seperti anggota. Sebagai PJ, Anda juga dapat mengisi Solusi dan Rencana Tindak Lanjut di sini.'],
            },
            {
                title: 'Isi fokus minggu ini untuk setiap anggota',
                where: 'Menu Kegiatan → Rencana Minggu Ini',
                route: 'weekly-plan.index',
                body: [
                    'Isi fokus setiap anggota sebelum Senin, lalu klik Simpan di kartu anggota itu. Kosongkan teksnya untuk menghapus fokus.',
                    'Kartu juga menunjukkan RK anggota yang belum punya kegiatan di triwulan ini dan kegiatan yang belum selesai. Pakai daftar itu untuk memilih fokus.',
                ],
            },
            {
                title: 'Buat rencana untuk anggota',
                where: 'Menu Kegiatan → Rencana Minggu Ini',
                route: 'weekly-plan.index',
                body: [
                    'Pada kartu anggota, klik "Tambah rencana" untuk menugaskan pekerjaan. Anggota menerima notifikasi, dan rencana itu tampak berlabel "Dari PJ".',
                    'Anda dapat mengubah atau membatalkan rencana siapa pun di tim selama belum dikirim ke kipApp. Tidak ada langkah persetujuan.',
                ],
            },
            {
                title: 'Buka Rekap Tim mingguan',
                where: 'Menu Rekap Tim → Mingguan',
                route: 'team-recap.weekly',
                body: [
                    'Pilih tim dan minggu. Setiap projek tampil sebagai satu kartu, dan setiap kegiatan anggota menjadi satu baris.',
                    'Bagian "Kelengkapan anggota" menunjukkan siapa yang belum mengisi klaim. Ingatkan mereka sebelum rapat.',
                ],
                shot: SHOTS.pjRekapTim,
            },
            {
                title: 'Tinjau dan rapikan setiap baris',
                body: [
                    'Setiap baris menampilkan anggota, uraian dari kipApp, RK-nya, lalu Target, Realisasi, Satuan dan Capaian.',
                    'Permasalahan, Solusi dan RTL sudah terisi dari yang ditulis anggota. Cukup rapikan, dan isi Parafrase PJ bila uraian anggota perlu diringkas.',
                    'Jika angka anggota keliru, ubah langsung target, realisasi atau satuan di barisnya. Baris itu bertanda "Angka dikoreksi". Jika anggota menyimpan ulang klaimnya, angka anggota berlaku lagi.',
                    'Tombol "Perlu perhatian" menyaring baris dengan capaian di bawah 100% atau yang ada permasalahan.',
                ],
            },
            {
                title: 'Gabungkan pekerjaan yang sama',
                body: [
                    'Centang baris-baris dalam satu projek, lalu klik "Gabungkan yang dipilih". Baris itu menjadi satu baris "Gabungan" dengan satu parafrase.',
                    'Target dan realisasi baris gabungan adalah total semua kegiatan yang digabung, dan Capaian dihitung dari total itu. Uraian asli tiap anggota tampil kecil di bawah nama anggota.',
                    'Klik "Batalkan penggabungan" untuk mengembalikan setiap kegiatan menjadi baris sendiri.',
                ],
            },
            {
                title: 'Simpan setiap projek',
                body: [
                    'Klik "Simpan" di bawah kartu projek. Seluruh baris projek itu masuk ke "Laporan Tersimpan", dan Anda tidak perlu menulis ringkasan mingguan lagi.',
                    'Laporan Tersimpan menampilkan baris bernomor per projek dengan hasil (misalnya 3/3 laporan), Permasalahan, Solusi dan RTL. Pakai bagian ini saat rapat.',
                ],
            },
            {
                title: 'Tambah bukti rapat',
                body: ['Tambahkan link Notula, Dokumentasi (foto) dan Daftar Hadir rapat tim.'],
            },
            {
                title: 'Kunci rekap setelah rapat',
                body: [
                    'Tombol "Kunci rekap" ada di bagian bawah halaman. Setelah dikunci, klaim anggota, angka dan isi laporan minggu itu tidak dapat diubah lagi.',
                    'Kunci rekap setelah rapat selesai. Tekan "Buka kunci" jika masih ada koreksi.',
                ],
            },
            {
                title: 'Rekap bulanan dan triwulanan (FRA)',
                where: 'Menu Rekap Tim → Bulanan / Triwulanan (FRA)',
                route: 'team-recap.monthly',
                body: [
                    'Bagian atas halaman bulanan menampilkan rekap setiap minggu (triwulanan: setiap bulan). Buka satu minggu untuk membacanya.',
                    'Ringkasan Bulanan (atau Triwulanan) berisi satu entri per projek: Uraian, Permasalahan, Solusi dan RTL. Klik "Pre-fill dari … minggu" untuk menyusun draf dari rekap mingguan. Rapikan lalu klik Simpan.',
                    'Rincian per RK ada di bagian bawah dan terlipat. Klik untuk membukanya, dipakai untuk konfirmasi, Excel dan FRA.',
                    'Klik "Isi dari mingguan" (bulanan) atau "Isi dari bulanan" (triwulanan) untuk mengisi Uraian, Permasalahan, Solusi dan RTL semua baris sekaligus. Hanya kolom yang masih kosong yang diisi.',
                    'Parafrase mingguan satu baris juga dapat ditarik dengan "Tarik dari mingguan" di panel baris itu.',
                    'Konfirmasi setiap baris. "Konfirmasi semua capaian 100%" mengonfirmasi semua baris yang sudah 100% sekaligus.',
                    'Di triwulanan (FRA), isi juga link bukti tindak lanjut, PIC dan batas waktu.',
                    'Kunci rekap setelah rapat selesai, sama seperti mingguan. Tombolnya ada di bagian bawah halaman bulanan.',
                ],
                shot: SHOTS.pjRekapBulanan,
                tip: 'PIC sebuah RK boleh menulis draf parafrase untuk RK-nya. Konfirmasi, bukti dan kunci tetap oleh PJ.',
            },
            {
                title: 'Unduh Excel untuk rapat',
                body: ['"Unduh Excel" menghasilkan satu sheet dengan format Rapat Mingguan / Rapat Bulanan / FRA untuk semua tim yang dapat Anda lihat.'],
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
        summary: 'Anda membaca rekap seluruh kantor per minggu, bulan atau triwulan dan menulis Catatan Pimpinan. Isi rekap tetap diubah oleh PJ.',
        replaces: 'Membaca sheet "Rapat Mingguan" dan "Rapat Bulanan"',
        steps: [
            {
                title: 'Buka Review Bersama',
                where: 'Menu Rekap Tim → Review Bersama',
                route: 'team-recap.overview',
                body: [
                    'Pilih Mingguan, Bulanan atau Triwulanan, lalu pilih periode dengan ‹ dan ›.',
                    'Kotak ringkasan menunjukkan jumlah tim yang sudah punya rekap, rata-rata capaian kantor, jumlah rekap yang sudah dikunci PJ, dan jumlah baris yang sudah dikonfirmasi.',
                ],
                shot: SHOTS.pimpinanReviewBersama,
            },
            {
                title: 'Baca tabel per tim',
                body: [
                    'Setiap baris adalah satu tim: PJ (penanggung jawab), jumlah baris RK, rata-rata capaian, baris dikonfirmasi, dan status Dikunci / Terbuka.',
                    'Klik panah di depan nama tim untuk melihat semua projek tim itu beserta PIC / ketua projek, jumlah anggota, baris RK dan capaian per projek.',
                    'Pada periode mingguan ada kolom "Anggota lengkap".',
                    'Kolom Status: Tercapai (capaian 100% atau lebih), Progres (70% sampai di bawah 100%), Rendah (di bawah 70%).',
                    'Saring dengan pilihan tim dan tombol status di atas tabel, misalnya hanya tim yang Rendah.',
                    '"Belum ada data" berarti belum ada klaim tersimpan, bukan capaian 0%.',
                ],
                tip: 'Rekap yang sudah "Dikunci" berarti PJ sudah menyiapkannya untuk rapat dan isinya tidak berubah lagi.',
            },
            {
                title: 'Tulis Catatan Pimpinan',
                body: [
                    'Klik "Tulis catatan" di kolom Catatan Pimpinan untuk tim, atau di tabel projek untuk satu projek.',
                    'Catatan berlaku untuk periode yang sedang dibuka dan tetap bisa ditulis walaupun rekap sudah dikunci PJ.',
                    'Untuk menghapus catatan, kosongkan isinya lalu klik Simpan.',
                ],
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
                title: 'Lihat laporan dan data master',
                where: 'Menu Laporan dan Data Master',
                route: 'laporan.pegawai',
                body: ['Laporan Pegawai, Proyek, IKU dan Rencana Kinerja dapat dibaca untuk semua tim.'],
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
                title: 'Sinkronkan struktur, kegiatan, lalu Angka Kredit',
                body: [
                    'Jalankan berurutan: 1. Struktur, 2. Kegiatan, 3. Angka Kredit. Selama satu sinkronisasi berjalan, tombol lain tidak aktif.',
                    'Tekan Sinkronkan pada Sinkronisasi Struktur lebih dulu. Langkah ini menarik tim, projek, anggota dan RK, termasuk ID pegawai kipApp.',
                    'Setelah selesai, tekan Sinkronkan pada Sinkronisasi Kegiatan. Kegiatan semua bulan dalam periode ikut ditarik, termasuk yang sudah dikirim.',
                    'Keduanya juga berjalan otomatis setiap pagi: Struktur pukul 04.30 WITA dan Kegiatan pukul 05.00 WITA. Jadwal ini memakai Task Scheduler di panel hosting, yang menjalankan php artisan schedule:run setiap 5 menit.',
                    'Sinkronisasi Angka Kredit menarik golongan, jabatan dan predikat SKP semua tahun untuk halaman Angka Kredit. Jalankan sesudah Sinkronisasi Struktur. Proses ini memanggil kipApp sekitar tiga kali per pegawai, jadi perlu beberapa menit. Biarkan halaman tetap terbuka sampai selesai.',
                    'Sinkronisasi Angka Kredit juga berjalan otomatis setiap Senin pukul 05.30 WITA.',
                    'Token kipApp berlaku sekitar 24 jam. Kinetik mengirim notifikasi ke admin tiga jam sebelum token kedaluwarsa dan sekali lagi saat sudah kedaluwarsa. Simpan token baru di halaman Integrasi kipApp.',
                ],
                tip: 'Sinkronisasi hanya menambah dan memperbarui data. Klaim, parafrase, konfirmasi, kunci dan bukti rapat tidak pernah dihapus. Satu pengecualian: anggota projek disamakan dengan daftar di kipApp.',
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
                title: 'Angka Kredit dari PAK',
                where: 'Menu Rekap Tim → Angka Kredit Tim',
                route: 'credit.team',
                body: [
                    'Golongan dan predikat SKP ditarik dari kipApp setiap Senin pagi, atau saat admin menekan Sinkronkan pada Sinkronisasi Angka Kredit di halaman Integrasi kipApp. Tanpa PAK, AK dihitung dari SKP sejak golongan atau jenjang saat ini.',
                    'Pegawai dapat mengisi AK dari PAK terakhirnya sendiri di Angka Kredit Saya. Baris itu bertanda "PAK diisi pegawai, belum dicek".',
                    'Cocokkan dengan dokumen PAK, lalu klik ikon pensil di baris pegawai dan simpan nilainya. Tandanya berubah menjadi "PAK dicek admin". AK sesudah tanggal PAK ditambahkan dari predikat kipApp.',
                    'Kosongkan kedua kolom untuk kembali ke estimasi.',
                ],
            },
        ],
    },
];

export const GUIDE_FAQ = [
    {
        q: 'Rencana Kinerja pada kegiatan saya kosong. Apa yang harus dilakukan?',
        a: 'Kinetik mencocokkan RK dari kipApp dengan RK tim Anda. Jika tidak ditemukan, formulir menampilkan pilihan "Pilih RK": pilih RK yang sesuai lalu simpan. Jika RK Anda tidak ada di daftar, minta admin menjalankan Sinkronisasi Struktur.',
    },
    {
        q: 'Saya tidak bisa menyimpan klaim: "Rekap tim untuk periode ini sudah dikunci PJ".',
        a: 'PJ sudah mengunci rekap untuk rapat. Minta PJ membuka kunci, simpan koreksi Anda, lalu PJ mengunci lagi.',
    },
    {
        q: 'Mengapa saya tidak bisa mengisi Solusi dan Rencana Tindak Lanjut?',
        a: 'Solusi dan RTL dibahas saat rapat dengan PJ, sehingga hanya PJ yang mengisinya.',
    },
    {
        q: 'Apa arti "Belum ada data"?',
        a: 'Belum ada klaim tersimpan untuk periode itu. Ini berbeda dengan capaian 0%.',
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
