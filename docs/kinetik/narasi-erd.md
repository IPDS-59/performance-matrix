# Narasi ERD Kinetik — Skrip untuk Presentasi

> Skrip untuk menjelaskan alur data (ERD `erd-kinetik.dbml`) secara lisan.
> Tanda **(tunjuk …)** = arahkan ke bagian diagram. Total ± 4–5 menit.
> Gaya: santai, kalimat pendek, mudah diucapkan.

---

## 0. Pembuka (± 20 detik)

"Baik, izinkan saya jelaskan alur datanya lewat diagram ini. Intinya, aplikasi
**Kinetik** menyatukan tiga hal: **data master** (tim, projek, rencana kinerja),
**tarikan data harian dari kipApp**, dan **rekap** mingguan sampai triwulanan.
Saya akan jalan dari kiri ke kanan, mengikuti perjalanan satu data kegiatan."

---

## 1. Bagian Master (± 60 detik)

**(tunjuk grup _master_)**

"Kita mulai dari struktur organisasinya.

- Ada **Tim** — ini unit kerja. Setiap tim punya **Ketua Tim**.
- Pegawai disimpan di tabel **Employees**. Satu pegawai bisa tergabung di
  beberapa tim, jadi keanggotaannya kita catat di tabel penghubung
  **employee_team** — relasinya **banyak-ke-banyak**.
- Tiap tim punya **IKU** (Indikator Kinerja Utama), lalu di bawah IKU ada
  **Projek** atau Program Kerja, dan di bawah projek ada **Rencana Kinerja** —
  kita singkat **RK**. Ini berjenjang: satu tim banyak projek, satu projek
  banyak RK.

**(tunjuk project_members)**

Nah, yang penting di sini: **Anggota Projek**. Satu anggota bisa ditugaskan ke
projek tertentu, dan ini bisa **kita tarik otomatis dari kipApp**. Jadi siapa
mengerjakan projek apa, sudah jelas."

> **Poin kunci:** Tim → IKU → Projek → RK itu **cascading** (berjenjang), dan
> keanggotaan tim itu **many-to-many**.

---

## 2. Bagian Sinkron kipApp (± 60 detik)

**(tunjuk grup _sinkron_kipapp_)**

"Sekarang dari mana datanya? Dari **kipApp**.

- Setiap pegawai sudah menginput **kegiatan harian** mereka di kipApp.
- Aplikasi kita menariknya secara terpusat. Kuncinya tabel **kip_credentials** —
  cukup **satu token admin**, kita bisa menarik data **semua** pegawai. Token ini
  disimpan terenkripsi dan berlaku sekitar 24 jam.
- Hasil tarikan masuk ke tabel **kip_activities** — ini uraian harian mentah:
  tanggal, jam, uraian kegiatan, link bukti dukung, dan progres dari kipApp.

Satu catatan penting soal **Target**: di kipApp, target itu **ada**, tapi
ditulis sebagai **teks di indikator (IKI)** — misalnya "Sebanyak 4 dokumen" —
bukan angka tersendiri. Untuk **Realisasi**, kipApp hanya punya **progres**
(0–100%). Jadi angka Target dan Realisasi per kegiatan tetap kita **lengkapi
di aplikasi kita** saat di-claim."

---

## 3. Rekap Mingguan Pegawai (± 60 detik)

**(tunjuk tabel activity_claims)**

"Di sinilah perpindahan pentingnya. Pegawai membuka menu **Rekap Mingguan**,
melihat kegiatan seminggu terakhir dari kipApp, lalu **men-claim** tiap kegiatan
ke **RK** yang sesuai.

Saat claim, pegawai melengkapi: **Target, Realisasi, Capaian** — capaiannya
otomatis dari rumus realisasi dibagi target — lalu **Kendala, Solusi, dan
Rencana Tindak Lanjut**. Setelah lengkap, klik **Simpan**.

Hasilnya tersimpan di tabel **activity_claims** — inilah **rekap mingguan per
pegawai**. Perhatikan relasinya: tiap claim **menunjuk ke RK** (jadi terhubung ke
projek dan tim), dan **menunjuk ke kegiatan kipApp** asalnya."

---

## 4. Rekap Tim & Bukti Rapat (± 45 detik)

**(tunjuk team_recap_evidences)**

"Dari rekap individu, otomatis muncul **Rekap Mingguan Tim** — gabungan semua
anggota dalam satu tim, **dipisah per projek**.

Rekap tim ini menjadi bahan **Rapat Mingguan**. Di situ, **PJ** melampirkan bukti
rapat di tabel **team_recap_evidences**: **Notula**, **Dokumentasi** berupa foto,
dan **Daftar Hadir**."

---

## 5. Rekap Bulanan, Triwulanan & Parafrase (± 60 detik)

**(tunjuk recap_overrides)**

"Naik ke level berikutnya:

- **Rekap Bulanan** — gabungan rekap mingguan dalam satu bulan. Di sini kendala,
  solusi, dan tindak lanjut bisa **diparafrase** supaya lebih ringkas. Parafrase
  ini disimpan di tabel **recap_overrides**, jadi data asli tidak hilang.
- **Rekap Triwulanan** mengikuti **format FRA**: selain parafrase, ditambah
  **link bukti tindak lanjut**, **PIC**, dan **batas waktu**.

**(tunjuk recap_documents)**

Dan ini usulan kami: tabel **recap_documents**. Karena rekap dipakai sebagai
**dokumen rapat**, kami usulkan ada tombol **Finalize**. Saat PJ menekan
Finalize, isi rekap di-**snapshot** — jadi terkunci dan tidak ikut berubah lagi —
lalu **otomatis tersalin** ke ringkasan bulanan untuk **Rapat Bulanan bersama
Kepala Kantor**."

---

## 6. Penutup (± 20 detik)

"Jadi ringkasnya, satu data mengalir: **dari kegiatan harian di kipApp**, ditarik
ke aplikasi, **di-claim jadi rekap mingguan pegawai**, **digabung jadi rekap
tim**, lalu **naik ke bulanan dan triwulanan** — semuanya jadi dokumen rapat yang
rapi dan bisa ditelusuri. Terima kasih, saya siap menerima pertanyaan."

---

### Versi singkat 30 detik (kalau waktu mepet)

"Alurnya satu arah: pegawai input kegiatan harian di **kipApp** → aplikasi
**menarik** semua data lewat satu token admin → pegawai **men-claim** ke RK dan
mengisi target, realisasi, kendala, solusi, tindak lanjut → otomatis jadi **rekap
mingguan tim per projek** plus bukti rapat → lalu **diparafrase** jadi **rekap
bulanan** dan **triwulanan FRA** sebagai bahan rapat. Master datanya — tim, IKU,
projek, RK — semua berjenjang, dan keanggotaan tim banyak-ke-banyak."
