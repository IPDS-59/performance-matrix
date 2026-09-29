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
        alt: 'Halaman Rekap Tim mingguan dengan kotak Siap rapat dan Kelengkapan anggota',
        width: 1024, height: 1331,
        markers: [
            { n: 1, x: 2.6, y: 15, label: 'Siap rapat: empat langkah sebelum rapat. Klik untuk menuju bagiannya.' },
            { n: 2, x: 85.1, y: 26.6, label: 'Kunci rekap setelah semua siap.' },
            { n: 3, x: 2.6, y: 32.3, label: 'Kelengkapan anggota: siapa yang belum mengisi.' },
            { n: 4, x: 1.2, y: 53.9, label: 'Baris rekap per Projek dan RK. Buka panah untuk mengisi Solusi dan RTL.' },
            { n: 5, x: 2.6, y: 63.8, label: 'Ringkasan Mingguan PJ untuk rapat dengan pimpinan.' },
            { n: 6, x: 1.2, y: 89.5, label: 'Bukti rapat: Notula, Dokumentasi, Daftar Hadir.' },
            { n: 7, x: 81.5, y: 9.2, label: 'Unduh Excel format Rapat Mingguan.' },
        ],
    },
    pjRekapBulanan: {
        src: '/images/pedoman/pj-rekap-bulanan.webp',
        alt: 'Halaman Rekap Tim bulanan dengan tombol konfirmasi',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 2.6, y: 14.6, label: 'Perlu perhatian: saring baris yang harus ditinjau.' },
            { n: 2, x: 18.3, y: 14.6, label: 'Konfirmasi semua baris yang capaiannya 100%.' },
            { n: 3, x: 86.8, y: 58.3, label: 'Konfirmasi satu baris.' },
            { n: 4, x: 93.1, y: 58.6, label: 'Buka panel parafrase kendala, solusi dan RTL.' },
            { n: 5, x: 85.1, y: 40.4, label: 'Kunci rekap bulan ini sebelum rapat.' },
        ],
    },
    pimpinanSemuaTim: {
        src: '/images/pedoman/pimpinan-semua-tim.webp',
        alt: 'Halaman Review Bersama untuk pimpinan',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 2.6, y: 7.2, label: 'Pilih Mingguan, Bulanan atau Triwulanan.' },
            { n: 2, x: 41.2, y: 7.2, label: 'Pindah periode dengan ‹ dan ›.' },
            { n: 3, x: 1.2, y: 17.8, label: 'Ringkasan kantor: tim, capaian, rekap dikunci, baris dikonfirmasi.' },
            { n: 4, x: 51.4, y: 32, label: 'Rata-rata capaian setiap tim.' },
            { n: 5, x: 81.3, y: 31.9, label: 'Status: Dikunci berarti siap untuk rapat.' },
            { n: 6, x: 92.3, y: 32, label: 'Buka rekap lengkap tim itu.' },
            { n: 7, x: 74.8, y: 7.2, label: 'Unduh Excel semua tim untuk rapat.' },
        ],
    },
    adminIntegrasi: {
        src: '/images/pedoman/admin-integrasi.webp',
        alt: 'Halaman Integrasi kipApp dengan status token dan tombol sinkronisasi',
        width: 1024, height: 836,
        markers: [
            { n: 1, x: 3.4, y: 7.3, label: 'Status token: masih berlaku atau kedaluwarsa.' },
            { n: 2, x: 3.4, y: 73.1, label: 'Tempel token x-auth baru dari kipApp.' },
            { n: 3, x: 17, y: 82.7, label: 'Simpan token.' },
            { n: 4, x: 36, y: 39.1, label: 'Sinkronisasi Struktur: tim, projek, anggota, RK.' },
            { n: 5, x: 81, y: 40.1, label: 'Jalankan struktur lebih dulu.' },
            { n: 6, x: 36, y: 7.3, label: 'Sinkronisasi Kegiatan: kegiatan harian semua pegawai.' },
            { n: 7, x: 81, y: 8.3, label: 'Lalu jalankan kegiatan.' },
            { n: 8, x: 57, y: 22.6, label: 'Hasil sinkronisasi terakhir.' },
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
                title: 'Isi kegiatan harian di kipApp',
                body: [
                    'Catat setiap kegiatan harian di kipApp seperti biasa, lengkap dengan uraian dan link bukti dukung.',
                    'Kinetik menarik data kipApp secara otomatis setiap pagi.',
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
                    'Pilih Projek tempat kegiatan ini dikerjakan. Jika Anda hanya ada di satu projek, projek itu sudah terpilih.',
                    'Isi Target, Realisasi dan Satuan. Capaian dihitung otomatis.',
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
                    'Kegiatan Saya menampilkan semua kegiatan kipApp Anda dan status klaimnya.',
                    'Rekap Tim menampilkan rekap tim Anda. Anda dapat membaca, tetapi hanya PJ yang mengubah ringkasan.',
                ],
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
                title: 'Buka Rekap Tim mingguan dan cek "Siap rapat"',
                where: 'Menu Rekap Tim → Mingguan',
                route: 'team-recap.weekly',
                body: [
                    'Kotak "Siap rapat" menunjukkan empat langkah: anggota lengkap, ringkasan PJ, bukti rapat, kunci rekap.',
                    'Klik sebuah langkah untuk langsung menuju bagiannya.',
                ],
                shot: SHOTS.pjRekapTim,
            },
            {
                title: 'Pastikan anggota sudah lengkap',
                body: [
                    'Bagian "Kelengkapan anggota" menampilkan setiap anggota: Lengkap, Sebagian, Belum mengisi, atau Tidak ada kegiatan.',
                    'Ingatkan anggota yang masih "Belum mengisi" atau "Sebagian" sebelum rapat.',
                ],
            },
            {
                title: 'Tinjau baris rekap',
                body: [
                    'Rekap dikelompokkan per Projek, lalu per Rencana Kinerja. Angka Target, Realisasi dan Capaian dijumlah dari klaim anggota.',
                    'Tombol "Perlu perhatian" menyaring baris dengan capaian di bawah 100%, ada kendala, atau belum dikonfirmasi.',
                    'Buka panah di ujung baris untuk melihat uraian dan kendala anggota, lalu isi Solusi dan RTL hasil rapat tim.',
                ],
            },
            {
                title: 'Isi Ringkasan Mingguan PJ',
                body: [
                    'Isi uraian kegiatan tim, kendala, solusi dan RTL minggu ini.',
                    '"Pre-fill dari data anggota" mengisi draf dari klaim anggota. Rapikan sebelum menyimpan.',
                ],
            },
            {
                title: 'Tambah bukti rapat',
                body: ['Tambahkan link Notula, Dokumentasi (foto) dan Daftar Hadir rapat tim.'],
            },
            {
                title: 'Kunci rekap sebelum rapat',
                body: [
                    'Tekan "Kunci rekap". Setelah dikunci, ringkasan, konfirmasi, bukti dan klaim anggota di periode itu tidak dapat diubah.',
                    'Tekan "Buka kunci" jika masih ada koreksi.',
                ],
            },
            {
                title: 'Rekap bulanan dan triwulanan (FRA)',
                where: 'Menu Rekap Tim → Bulanan / Triwulanan (FRA)',
                route: 'team-recap.monthly',
                body: [
                    'Parafrase mingguan dapat ditarik ke bulanan dengan "Tarik dari mingguan".',
                    'Konfirmasi setiap baris. "Konfirmasi semua capaian 100%" mengonfirmasi semua baris yang sudah 100% sekaligus.',
                    'Di triwulanan (FRA), isi juga link bukti tindak lanjut, PIC dan batas waktu.',
                    'Kunci rekap sebelum rapat, sama seperti mingguan.',
                ],
                shot: SHOTS.pjRekapBulanan,
                tip: 'PIC sebuah RK boleh menulis draf parafrase untuk RK-nya. Konfirmasi, bukti dan kunci tetap oleh PJ.',
            },
            {
                title: 'Unduh Excel untuk rapat',
                body: ['"Unduh Excel" menghasilkan satu sheet dengan format Rapat Mingguan / Rapat Bulanan / FRA untuk semua tim yang dapat Anda lihat.'],
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
                shot: SHOTS.pimpinanSemuaTim,
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
                title: 'Sinkronkan struktur, lalu kegiatan',
                body: [
                    'Tekan Sinkronkan pada Sinkronisasi Struktur lebih dulu. Langkah ini menarik tim, projek, anggota dan RK, termasuk ID pegawai kipApp.',
                    'Setelah selesai, tekan Sinkronkan pada Sinkronisasi Kegiatan. Kegiatan semua bulan dalam periode ikut ditarik, termasuk yang sudah dikirim.',
                    'Keduanya juga berjalan otomatis setiap pagi dengan urutan yang sama.',
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
        ],
    },
];

export const GUIDE_FAQ = [
    {
        q: 'Rencana Kinerja pada kegiatan saya kosong. Apa yang harus dilakukan?',
        a: 'RK belum cocok dengan data kipApp. Tunggu sinkronisasi berikutnya atau minta admin menyinkronkan ulang. Jika tetap kosong, periksa RK kegiatan itu di kipApp.',
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
