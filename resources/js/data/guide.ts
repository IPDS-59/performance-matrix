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

export const GUIDE_OVERVIEW = [
    { who: 'Anggota', what: 'Merencanakan kegiatan dan mengisi capaian mingguan di Rekap Mingguan.' },
    { who: 'PJ / Ketua Tim', what: 'Meninjau rekap tim, mengisi solusi dan rencana tindak lanjut, menambah bukti rapat, lalu mengunci rekap sebelum rapat.' },
    { who: 'Pimpinan', what: 'Membaca rekap semua tim di Review Bersama, menulis Catatan Pimpinan, dan mengunduh Excel untuk rapat.' },
];

export const GUIDE_FLOWS: GuideFlow[] = [
    {
        role: 'anggota',
        label: 'Anggota',
        summary: 'Setiap minggu Anda merencanakan kegiatan per Rencana Kinerja (RK), lalu mengisi capaian (angka dan uraian) dan tautan bukti.',
        replaces: 'Sheet "Kegiatan Mingguan Anggota"',
        steps: [
            SSO_LOGIN_STEP,
            {
                title: 'Buka Rekap Mingguan',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Halaman ini dibuka dengan daftar bulan. Tiap baris menunjukkan jumlah kegiatan, berapa yang capaiannya sudah terisi, dan berapa yang sudah terkirim ke atasan. Pilih satu bulan untuk melihat minggu-minggunya, lalu pilih satu minggu. Baris dengan label "Sekarang" adalah bulan atau minggu berjalan. Tombol "Semua bulan" dan "Minggu di …" membawa Anda kembali satu langkah.',
                    'Di dalam sebuah minggu ada dua tabel dan hanya kegiatan Anda sendiri: "Capaian minggu lalu" dan "Rencana minggu ini". Kolom cari menyaring tabelnya.',
                    'Jika PJ mengisi fokus untuk Anda, fokus itu tampil di atas tabel.',
                    'Tekan "Sinkronkan data saya" untuk menarik RK dan kegiatan Anda dari kipApp. Sinkronisasi pertama berjalan otomatis setelah Anda menghubungkan akun SSO. Jika daftar RK kosong, tekan tombol ini.',
                ],
            },
            {
                title: 'Rapat minggu pertama: isi target',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Tekan "Tambah". Isi seperti di kipApp: pilih RK (bisa dicari), tulis kegiatan (minimal 10 karakter), isi progres, tautan bukti dukung dan uraian capaian.',
                    'Ada dua kotak centang yang berdiri sendiri: "Gunakan periode tanggal" (tanggal mulai sampai selesai) dan "Gunakan jam" (jam mulai dan selesai, per 15 menit). Keduanya boleh dipakai bersamaan. Tanggal harus berada dalam satu triwulan.',
                    'Tidak yakin menulis apa? Tekan "List Aktivitas" di samping kolom Kegiatan. Pilih satu baris presensi dari kipApp (tekan tombol + atau klik dua kali). Kegiatan dan tanggalnya terisi, sisanya tetap Anda isi sendiri.',
                    'Kotak "Masukan ke capaian SKP" menentukan apakah kegiatan dihitung sebagai capaian SKP di kipApp. Biarkan menyala untuk sedikitnya satu kegiatan tiap RK.',
                    'Membuka form untuk tanggal yang sudah lewat membuat progres langsung 100, seperti di kipApp. Untuk rencana ke depan, progres mulai dari 0.',
                    'Isi juga Target (angka) dan Satuan. Capaian (angka) boleh diisi nanti. Target dan capaian selalu angka; teks penjelasan ditulis di uraian capaian.',
                    'Pilih Projek bila RK itu belum punya projek. Anda dapat mengubah atau membatalkan kegiatan selama belum dikirim ke kipApp. Untuk membuat kegiatan serupa, klik barisnya lalu tekan "Salin kegiatan": form terisi dari kegiatan itu.',
                ],
            },
            {
                title: 'Rapat berikutnya: tinjau capaian lalu rencanakan',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Pada rapat minggu kedua dan seterusnya, mulai dari tabel "Capaian minggu lalu". Klik baris untuk mengisi capaian (angka), uraian capaian dan tautan bukti. Persentase capaian terhadap target dihitung otomatis.',
                    'Lalu tambahkan kegiatan untuk "Rencana minggu ini".',
                ],
            },
            {
                title: 'Kirim ke kipApp dan tandai selesai',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: [
                    'Klik baris kegiatan untuk melihat semua isinya dan langkah berikutnya.',
                    'Pada tanggal mulai, Kinetik membuat kegiatan di kipApp atas nama Anda. Pengiriman berjalan otomatis setiap jam kerja mulai pukul 05.10 WITA. Ingin lebih awal? Tekan "Kirim ke kipApp".',
                    'Awal triwulan baru belum ada SKP triwulannya di kipApp. Kinetik membuatnya otomatis dari SKP Tahunan Anda, seperti tombol "Buat SKP Periodik" di kipApp, dengan RK yang sama seperti triwulan sebelumnya. Ini hanya jalan bila SKP Tahunan sudah berstatus Ditetapkan.',
                    'Jika belum terkirim, alasannya tampil di detail kegiatan, misalnya "SKP Tahunan belum Ditetapkan". Kinetik mencoba lagi pada jam berikutnya sampai tanggal selesai dan memberi tahu Anda satu kali lewat lonceng.',
                    'Kinetik tidak mengirim kegiatan sebelum tanggal mulainya, karena kipApp tidak menerima tanggal yang belum tiba. Tombol "Kirim ke kipApp" aktif pada hari itu.',
                    'Selesai mengerjakan? Tekan "Tandai selesai". Uraian capaian wajib minimal 10 karakter saat ini. Kinetik mengirim progres 100%, uraian capaian dan tautan bukti ke kipApp. Tombol "Simpan capaian" hanya menyimpan di Kinetik.',
                    'Kegiatan yang sudah ada di kipApp dan belum dikirim ke atasan bisa diubah, atau dihapus lewat tombol "Hapus kegiatan" di detail. Kinetik menghapusnya juga di kipApp, setelah bertanya dulu. Kegiatan yang sudah dikirim ke atasan tidak bisa dihapus: tekan "Batalkan kirim" dulu, lalu hapus. Jika SKP triwulan sudah dikirim untuk dinilai, kegiatan terkunci dan alasannya tampil di detail.',
                    'Mengirim ke atasan: centang beberapa kegiatan di tabel, lalu tekan "Kirim ke atasan (n)". Kinetik bertanya dulu, lalu mengirim semuanya sekaligus. "Batalkan kirim (n)" mengembalikan kegiatan yang sudah terkirim. Kirim dan batal kirim sudah diuji langsung di kipApp. Aturannya sama dengan kipApp: hanya berlaku selama SKP triwulan berstatus "Sedang dibuat".',
                    'Jika progres diubah langsung di kipApp, Kinetik mengikuti kipApp pada sinkronisasi berikutnya.',
                ],
                tip: 'Jika kata sandi SSO Anda ditolak BPS, Kinetik berhenti mengakses kipApp atas nama Anda dan meminta Anda memasukkannya lagi saat masuk. Kinetik tidak mencoba ulang dengan kata sandi yang sama.',
            },
            {
                title: 'Lihat hasilnya',
                where: 'Menu Kegiatan → Kegiatan Saya, dan menu Rekap Tim',
                route: 'kip-activities.index',
                body: [
                    'Kegiatan Saya menampilkan semua kegiatan kipApp Anda. Saring per Minggu, Bulan atau Triwulan, lalu pindah periode dengan ‹ dan ›.',
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
                    'Angka ini adalah ESTIMASI. Dasar hitungnya PermenPANRB Nomor 1 Tahun 2023 (Pasal 37 dan Lampiran A) dan PerBKN Nomor 3 Tahun 2023 (Pasal 13): koefisien tahunan jenjang dibagi 12, dikali persentase predikat, per bulan.',
                    'Yang belum ikut dihitung: AK dari luar predikat SKP (misalnya pendidikan, pengembangan kompetensi, penyesuaian atau konversi jabatan), serta pengurangan. AK di bulan sebelum ada SKP di kipApp hanya masuk lewat "AK dari PAK". Angka resmi tetap dari PAK yang ditetapkan Tim Penilai.',
                    'Angka tiap triwulan bisa diubah dengan tombol "Ubah" bila Anda punya dasar, misalnya dokumen PAK. Alasan wajib diisi. Triwulan itu ditandai "Diubah manual" dan PJ serta pimpinan melihat siapa yang mengubah, kapan, alasannya, dan angka hitungan otomatisnya. "Pakai hitungan otomatis" mengembalikannya.',
                ],
                shot: SHOTS.anggotaAngkaKredit,
                tip: 'Angka ini adalah pemantauan. Usulan kenaikan pangkat tetap memakai PAK resmi.',
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
            {
                title: 'Isi Rekap Mingguan Anda sendiri',
                where: 'Menu Kegiatan → Rekap Mingguan',
                route: 'weekly.index',
                body: ['Sama seperti anggota. Halaman ini hanya menampilkan kegiatan Anda sendiri; rekap anggota ada di menu Rekap Tim.'],
            },
            {
                title: 'Buka Rekap Tim mingguan',
                where: 'Menu Rekap Tim → Mingguan',
                route: 'team-recap.weekly',
                body: [
                    'Halaman ini dibuka dengan daftar bulan untuk tim yang dipilih. Pilih satu bulan, lalu satu minggu. Tiap baris menunjukkan jumlah kegiatan, berapa RK yang parafrasenya sudah ditulis, berapa yang terkunci, dan statusnya. Pemilih tim dan tahun ada di atas daftar.',
                    'Di dalam sebuah minggu, bagian "Kelengkapan anggota" menunjukkan siapa yang belum mengisi capaian di Rekap Mingguan. Ingatkan mereka sebelum rapat.',
                ],
            },
            {
                title: 'Lihat rencana minggu ini dari anggota',
                where: 'Menu Rekap Tim → Mingguan → pilih bulan dan minggu',
                body: [
                    'Di bagian atas sebuah minggu ada tabel "Rencana minggu ini": kegiatan semua anggota tim pada minggu itu, dengan anggota, RK, target dan status. Kolom cari dan pemilih anggota menyaring tabelnya.',
                    'Klik satu baris untuk membuka semua isinya (target, capaian, uraian, bukti dukung dan status). Tampilan ini hanya baca. Anggota mengubah kegiatannya di Rekap Mingguan mereka sendiri.',
                    'Untuk membahas capaian minggu lalu, kembali ke daftar minggu dan buka minggu sebelumnya.',
                ],
            },
            {
                title: 'Baca kontribusi anggota dan tulis parafrase',
                body: [
                    'Tiap RK tampil sebagai kartu ringkas: parafrase PJ, target RK, capaian RK, status kunci, dan tabel pendek anggota yang mengerjakannya. Tekan "Isi parafrase" (atau "Ubah parafrase") untuk membuka popup. Mengklik nama anggota di tabel juga membuka popup itu untuk anggota tersebut.',
                    'Di kiri popup ada kegiatan anggota untuk RK itu, satu baris per anggota. Klik nama untuk membukanya. Kolom cari dan "Belum dibaca saja" membantu menelusuri tim yang besar; "Dibaca n/m" menghitung yang sudah Anda buka. Filter "Lihat" menampilkan satu anggota saja.',
                    'Di kanan ada kotak Parafrase PJ yang kosong. Tulis sendiri, atau tekan "Sisipkan" pada satu kalimat anggota untuk mengambilnya. Kinetik tidak mengisi otomatis dari semua anggota.',
                    'Target dan capaian setiap anggota berasal dari Rekap Mingguan mereka dan tidak dapat diubah di sini.',
                ],
            },
            {
                title: 'Isi target, satuan dan capaian RK, lalu simpan atau kunci',
                body: [
                    'Masih di popup yang sama, isi satu Target (angka), satu Satuan dan satu Capaian (angka) untuk seluruh RK. Persentase capaian dihitung otomatis.',
                    '"Simpan" menyimpan tanpa mengunci dan menutup popup; hasilnya langsung tampil di kartu RK. "Simpan dan kunci" menyimpan lalu mengunci RK itu, sehingga tidak berubah lagi. "Buka kunci" membukanya kembali.',
                    'PJ dan PIC RK dapat menulis. Anggota lain hanya membaca.',
                ],
                tip: 'Kunci tiap RK setelah rapat selesai. Tekan "Buka kunci" jika masih ada koreksi.',
            },
            {
                title: 'Tambah bukti rapat',
                body: ['Tambahkan link Notula, Dokumentasi (foto) dan Daftar Hadir rapat tim.'],
            },
            {
                title: 'Rekap bulanan dan triwulanan (FRA)',
                where: 'Menu Rekap Tim → Bulanan / Triwulanan (FRA)',
                route: 'team-recap.monthly',
                body: [
                    'Halaman bulanan dibuka dengan daftar Januari sampai Desember. Pilih satu bulan untuk membuka rekapnya; tombol "Semua bulan" membawa Anda kembali ke daftar. Rekap sebuah bulan memakai kartu dan popup yang sama dengan mingguan, per RK. Parafrase mingguan bulan itu tampil di popup sebagai bahan, lengkap dengan tombol "Sisipkan".',
                    'Halaman triwulanan (FRA) dibuka dengan empat triwulan dalam setahun. Pilih satu untuk membuka rekapnya. Di dalamnya ada satu baris per RK dengan target dan capaian dari rekap bulanan. Jika bulanan belum diisi, angkanya dijumlahkan dari kegiatan anggota.',
                    'Buka panel sebuah baris untuk menulis uraian, permasalahan, solusi dan RTL. Isi juga link bukti tindak lanjut, PIC dan batas waktu.',
                    'Konfirmasi setiap baris. "Konfirmasi semua capaian 100%" mengonfirmasi semua baris yang sudah 100% sekaligus. Kunci triwulan setelah rapat selesai; tombolnya ada di bagian atas halaman.',
                ],
                tip: 'PIC sebuah RK boleh menulis parafrase untuk RK-nya. Konfirmasi, bukti dan kunci triwulan tetap oleh PJ.',
            },
            {
                title: 'Unduh Excel untuk rapat',
                body: ['"Unduh Excel" menghasilkan satu sheet dengan format Rapat Mingguan / Rapat Bulanan / FRA untuk semua tim yang dapat Anda lihat.'],
            },
            {
                title: 'Isi Kertas Kerja FRA untuk indikator tim Anda',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Kertas Kerja FRA memuat IKU dan proksi tingkat provinsi (sheet LK_Prov). Indikator yang timnya Anda pimpin tampil dengan ikon pensil.',
                    'Pilih triwulan, tekan pensil, lalu isi Alokasi target dan Realisasi (kumulatif sampai triwulan itu). Untuk indikator persen, isi X dan Y; Kinetik menghitung nilainya.',
                    'Isi juga Kendala, Solusi, Rencana tindak lanjut, PIC, Batas waktu dan link bukti. Kolom ini sama dengan kolom analisis di Kertas Kerja.',
                    'Capaian terhadap target triwulan dan setahun dihitung otomatis dengan rumus Kertas Kerja (maksimal 120).',
                ],
                tip: 'Tidak ada pensil pada indikator tertentu? Minta admin menetapkan tim Anda sebagai pemilik indikator itu.',
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
            SSO_LOGIN_STEP,
            {
                title: 'Buka Review Bersama',
                where: 'Menu Rekap Tim → Review Bersama',
                route: 'team-recap.overview',
                body: [
                    'Bagian "Perlu perhatian" di paling atas menunjukkan yang harus Anda lihat lebih dulu. Merah berarti perlu tindakan: kegiatan bulan ini yang belum punya target, kegiatan selesai tanpa capaian, anggota yang belum punya rencana minggu ini, dan kegiatan yang belum terkirim ke kipApp. Kuning berarti segera: tim yang rekap minggu lalunya belum dikunci. Biru memberi informasi: pegawai yang sudah siap atau hampir naik pangkat atau jenjang menurut Angka Kredit. Tiap kartu menampilkan beberapa nama dan tombol "Buka halamannya".',
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
                    '"Belum ada data" berarti belum ada rekap atau kegiatan pada periode itu, bukan capaian 0%.',
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
                title: 'Baca Kertas Kerja FRA dan unduh Excel',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Halaman ini menampilkan IKU dan proksi per sasaran, dengan alokasi, realisasi dan capaian terhadap target triwulan dan setahun, serta tim pemilik tiap indikator.',
                    'Kartu di atas menunjukkan Capaian IKU triwulan dan setahun, NKO rata-rata capaian PK, dan predikat PKO. Isi Nilai SAKIP agar koreksi predikat ikut dihitung.',
                    '"Unduh Excel" menghasilkan lembar LK_Prov dengan urutan baris dan kolom Kertas Kerja. Pimpinan hanya membaca; pengisian dilakukan PJ tim pemilik.',
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
                    'Tekan Sinkronkan pada Sinkronisasi Struktur lebih dulu. Langkah ini menarik tim, projek, anggota dan RK, termasuk ID pegawai kipApp.',
                    'Setelah selesai, tekan Sinkronkan pada Sinkronisasi Kegiatan. Kegiatan semua bulan dalam periode ikut ditarik, termasuk yang sudah dikirim.',
                    'Keduanya juga berjalan otomatis setiap pagi: Struktur pukul 04.30 WITA dan Kegiatan pukul 05.00 WITA. Jadwal ini memakai Task Scheduler di panel hosting, yang menjalankan php artisan schedule:run setiap 5 menit.',
                    'Sinkronisasi Angka Kredit menarik golongan, jabatan dan predikat SKP semua tahun untuk halaman Angka Kredit. Jalankan sesudah Sinkronisasi Struktur. Proses ini memanggil kipApp sekitar tiga kali per pegawai, jadi perlu beberapa menit. Biarkan halaman tetap terbuka sampai selesai.',
                    'Sinkronisasi Angka Kredit juga berjalan otomatis setiap Senin pukul 05.30 WITA.',
                    'Token kipApp berlaku sekitar 24 jam. Kinetik mengirim notifikasi ke admin tiga jam sebelum token kedaluwarsa dan sekali lagi saat sudah kedaluwarsa. Simpan token baru di halaman Integrasi kipApp.',
                ],
                tip: 'Sinkronisasi hanya menambah dan memperbarui data. Kegiatan Anda, parafrase, konfirmasi, kunci dan bukti rapat tidak pernah dihapus. Satu pengecualian: anggota projek disamakan dengan daftar di kipApp.',
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
                title: 'Tetapkan tim pemilik indikator FRA',
                where: 'Menu Rekap Tim → Kertas Kerja FRA',
                route: 'fra.index',
                body: [
                    'Setiap IKU dan proksi di Kertas Kerja FRA perlu tim pemilik. PJ tim itu yang mengisi alokasi, realisasi dan analisis triwulan.',
                    'Pada kolom Tim pemilik, pilih tim dari daftar (dapat dicari). Indikator tanpa pemilik hanya dapat diisi admin.',
                    'Data awal berasal dari Kertas Kerja TW 2 2026. Jalankan seeder FraIndicatorSeeder satu kali setelah migrasi; menjalankannya lagi tidak menimpa angka yang sudah diisi.',
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
        q: 'Mengapa Kinetik meminta kata sandi SSO saya?',
        a: 'Agar Kinetik dapat mengambil dan, nanti, mengirim kegiatan ke kipApp atas nama Anda tanpa Anda membuka kipApp. Kata sandi disimpan terenkripsi, tidak pernah ditampilkan kembali, dan Kinetik berhenti mencoba bila kata sandi ditolak satu kali.',
    },
    {
        q: 'Login ditolak padahal kata sandi SSO saya benar.',
        a: 'Masuk dengan username SSO (misalnya "nama") atau "nama@bps.go.id", bukan email lain. Jika baru mengganti kata sandi SSO, pakai yang baru. Jika masih gagal, pastikan akun SSO Anda dapat membuka kipApp, lalu hubungi admin.',
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
        q: 'PJ tidak bisa menyimpan parafrase RK: "Rekap RK ini sudah dikunci".',
        a: 'RK itu sudah dikunci setelah rapat. Tekan "Buka kunci" pada panel RK, simpan koreksi, lalu kunci lagi.',
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
