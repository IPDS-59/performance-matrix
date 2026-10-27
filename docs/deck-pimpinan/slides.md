---
theme: default
title: Kinetik - Perencanaan Kinerja Terintegrasi
info: Paparan untuk pimpinan BPS Provinsi Sulawesi Tengah
colorSchema: light
transition: fade
mdc: true
fonts:
  sans: Inter
  provider: none
---

# Kinetik

Perencanaan dan pelaporan kinerja tim yang terintegrasi

<div class="mt-12 opacity-80">
Paparan untuk Pimpinan<br>BPS Provinsi Sulawesi Tengah
</div>

<!--
Selamat pagi Bapak/Ibu pimpinan. Terima kasih atas waktunya.
Hari ini saya memaparkan Kinetik, aplikasi pelaporan kinerja tim yang kami kembangkan.
Tiga hal: apa yang sudah berjalan, bagaimana ini mendukung prioritas pimpinan di bidang perencanaan (FRA), dan apa langkah berikutnya.
Durasi sekitar 15 menit.
-->

---

# Arah paparan

<v-clicks>

1. Mengapa perencanaan perlu dibantu aplikasi
2. Apa yang **sudah berjalan** di Kinetik
3. Bagaimana Kinetik mendukung **perencanaan (FRA)**
4. Yang **direncanakan** berikutnya, risiko, dan permohonan

</v-clicks>

<!--
Saya pisahkan dengan tegas antara yang sudah berjalan dan yang masih rencana, supaya tidak ada salah paham.
-->

---

# Masalahnya

<div class="card">Staf sering belum tahu apa yang akan dikerjakan pada hari Senin.</div>
<div class="card">Perencanaan perlu ditunjukkan dalam FRA (Kertas Kerja triwulan, lembar LK_Prov).</div>
<div class="card">Aplikasi kipApp (aplikasi SKP BPS) hanya mencatat pekerjaan <b>setelah selesai</b>.</div>

<!--
Prioritas pimpinan adalah perencanaan. Tetapi kipApp tidak dirancang untuk merencanakan: ia mencatat hasil.
Akibatnya rencana tidak punya tempat, padahal FRA meminta bukti perencanaan.
-->

---
layout: center
class: text-center
---

# Fakta dari data produksi

<div class="grid grid-cols-2 gap-12 mt-8">
<div><div class="big">0</div>kegiatan kipApp bertanggal masa depan</div>
<div><div class="big">8.747 / 8.759</div>kegiatan sudah berprogres 100</div>
</div>

<div class="mt-8 opacity-70 text-base">Per 5 Oktober 2026</div>

<!--
Per 5 Oktober 2026: dari 8.759 kegiatan di kipApp, 8.747 sudah berprogres 100 persen, dan tidak ada satu pun yang bertanggal ke depan.
Artinya kipApp berisi catatan masa lalu. Rencana ke depan harus disimpan di tempat lain.
-->

---

# Gagasan Kinetik

Kinetik menyimpan **rencana sebelum pekerjaan**, lalu menautkannya ke catatan kipApp **sesudah pekerjaan**.

```mermaid {scale: 0.9}
flowchart LR
  A[Rencana<br/>Kinetik]:::p --> B[kipApp<br/>catatan kegiatan]
  B --> C[Rekap<br/>mingguan - bulanan - triwulanan]
  C --> D[FRA<br/>Kertas Kerja]
  classDef p fill:#FBF3E0,stroke:#B7791F
```

<div class="mt-4 text-base opacity-80">Tujuan akhir: pegawai tidak perlu membuka kipApp sama sekali.</div>

<!--
Alurnya: Rencana, kipApp, Rekap, FRA.
Kotak Rencana berwarna kuning karena penyambungan otomatis ke kipApp masih rencana. Bagian rekap sudah berjalan.
Tujuan akhirnya, pegawai cukup memakai Kinetik.
-->

---

# Sudah berjalan (1): data dan rekap

<div class="card ok"><span class="tag">Sudah</span> Sinkronisasi kegiatan dari kipApp setiap pagi, dan tombol <b>Sinkronkan data saya</b> untuk tiap pegawai</div>
<div class="card ok"><span class="tag">Sudah</span> <b>Rekap Mingguan</b> per anggota: klaim kegiatan dan bukti dukung</div>
<div class="card ok"><span class="tag">Sudah</span> <b>Rekap Bulanan</b> dan <b>Triwulanan</b> disusun dari data mingguan</div>
<div class="card ok"><span class="tag">Sudah</span> Ekspor ke Excel</div>

<!--
Kegiatan ditarik dari kipApp. Setiap anggota mengklaim kegiatannya pada rekap mingguan dan melampirkan bukti.
Rekap bulanan dan triwulanan tidak diketik ulang; disusun dari data mingguan.
Semua bisa diekspor ke Excel.
-->

---

# Sudah berjalan (2): peran Penanggung Jawab tim

<div class="card ok"><span class="tag">Sudah</span> <b>Rekap Tim</b>: baris per kegiatan, baris sejenis digabung menjadi blok <b>Gabungan</b></div>
<div class="card ok"><span class="tag">Sudah</span> Parafrase uraian, serta <b>Permasalahan, Solusi, dan RTL</b></div>
<div class="card ok"><span class="tag">Sudah</span> Rekap dapat <b>dikunci</b> setelah ditinjau</div>

<!--
PJ tim melihat seluruh kegiatan anggota, menggabungkan baris yang sejenis, menyederhanakan uraian, mengisi permasalahan, solusi dan rencana tindak lanjut, lalu mengunci rekap.
-->

---

# Sudah berjalan (3): perencanaan mingguan

<div class="card ok"><span class="tag">Sudah</span> Papan <b>Rencana Minggu Ini</b></div>
<div class="card ok"><span class="tag">Sudah</span> Fokus mingguan dari PJ</div>
<div class="card ok"><span class="tag">Sudah</span> Butir rencana per anggota; setiap butir wajib terkait <b>RK</b></div>
<div class="card ok"><span class="tag">Sudah</span> Notifikasi dalam aplikasi dan peringatan token kipApp hampir kedaluwarsa</div>

<!--
Ini bagian yang paling relevan dengan prioritas perencanaan. PJ menetapkan fokus minggu ini; anggota menyusun butir rencana. Setiap butir wajib terkait RK agar rencana bisa ditelusuri ke kinerja.
Peringatan token membantu admin mencegah sinkronisasi terhenti.
-->

---

# Sudah berjalan (4): masuk dengan akun SSO BPS

<div class="card ok"><span class="tag">Sudah</span> Pegawai masuk dengan <b>kata sandi akun SSO</b> masing-masing</div>
<div class="card ok"><span class="tag">Sudah</span> Login pertama dengan kata sandi bawaan, lalu <b>menghubungkan</b> akun kipApp</div>
<div class="card ok"><span class="tag">Sudah</span> Sinkronisasi per pengguna, memakai token pegawai sendiri bila masih berlaku</div>

**Keamanan:** kata sandi disimpan terenkripsi dengan kunci terpisah, tidak pernah ditampilkan, berdasarkan MoU. Kinetik tidak mencoba login ulang sendiri: login hanya terjadi saat pegawai masuk.

<!--
Kata sandi disimpan terenkripsi dengan kunci tersendiri, tidak pernah ditampilkan kembali, dan dasarnya adalah MoU yang ditandatangani pegawai.
Kinetik tidak mencoba login ulang secara otomatis, dan percobaan salah dibatasi, supaya akun BPS pegawai tidak terkunci.
Pesan untuk pimpinan: ini yang memungkinkan pegawai tidak membuka kipApp.
-->

---

# Dukungan untuk prioritas FRA: perencanaan

```mermaid {scale: 0.85}
flowchart LR
  A[Rencana Minggu Ini<br/>fokus PJ + butir anggota]:::ok --> B[Terkait RK<br/>wajib]:::ok
  B --> C[Rekap Mingguan<br/>Bulanan Triwulanan]:::ok
  C --> D[IKU - LK_Prov<br/>FRA]:::p
  classDef ok fill:#E7F5EC,stroke:#1F8A4C
  classDef p fill:#FBF3E0,stroke:#B7791F
```

<div class="mt-4 text-base"><span class="tag">Hijau</span> sudah berjalan &nbsp; <span class="tag p">Kuning</span> rencana</div>

<!--
Hijau sudah berjalan: rencana mingguan, keterkaitan wajib ke RK, dan rekap.
Kuning masih rencana: penyajian alokasi triwulanan IKU dan ekspor format LK_Prov untuk Kertas Kerja FRA.
-->

---

# Yang direncanakan (belum berjalan)

<div class="grid grid-cols-2 gap-4">
<div class="card plan"><span class="tag p">Rencana</span> Rencana dikirim ke kipApp pada tanggal mulai</div>
<div class="card plan"><span class="tag p">Rencana</span> Anggota menandai selesai dari Kinetik</div>
<div class="card plan"><span class="tag p">Rencana</span> Pengingat Senin 09.00 dan evaluasi Jumat sore</div>
<div class="card plan"><span class="tag p">Rencana</span> RTL menjadi rencana triwulan berikutnya</div>
<div class="card plan"><span class="tag p">Rencana</span> Alokasi triwulanan IKU dan ekspor LK_Prov</div>
<div class="card plan"><span class="tag p">Rencana</span> Dasbor kepatuhan perencanaan</div>
</div>
<div class="card plan mt-2"><span class="tag p">Rencana</span> Pembuatan SKP triwulan berikutnya dari Kinetik</div>

<!--
Semua yang di slide ini masih rencana, belum berjalan.
Urutan pengerjaan: keterkaitan rencana ke IKU, RTL menjadi rencana, lalu format LK_Prov dan dasbor kepatuhan. Penulisan ke kipApp bergantung pada penyambungan akun seluruh pegawai.
-->

---

# Risiko dan ketergantungan

<div class="card"><b>kipApp</b> adalah aplikasi internal tanpa kontrak antarmuka yang dipublikasikan. Perubahan rilis dapat mengganggu sinkronisasi.<br><small>Mitigasi: seluruh panggilan dipusatkan di satu kelas dan diuji.</small></div>
<div class="card"><b>Kata sandi dan token</b>: kebocoran berdampak ke akun BPS.<br><small>Mitigasi: kunci enkripsi terpisah, tidak ditampilkan atau dicatat, tidak ada login ulang otomatis.</small></div>
<div class="card"><b>Token kipApp</b> berlaku 24 jam; bila habis, sinkronisasi berhenti.<br><small>Mitigasi: peringatan ke admin sebelum kedaluwarsa.</small></div>

<!--
Kami terbuka soal risiko. Pertama, kipApp tidak punya kontrak yang dipublikasikan, jadi bisa berubah tanpa pemberitahuan. Kedua, keamanan kata sandi. Ketiga, token berumur 24 jam.
Masing-masing sudah ada mitigasinya.
-->

---

# Langkah berikutnya dan permohonan

<v-clicks>

1. **Persetujuan** menyambungkan akun SSO seluruh pegawai ke Kinetik
2. **Percontohan** pada satu tim, lalu dievaluasi
3. Penulisan rencana ke kipApp dan pengingat mingguan (rencana)
4. Keluaran format LK_Prov untuk Kertas Kerja FRA (rencana)

</v-clicks>

<!--
Permohonan kami: pertama, persetujuan untuk membuka penyambungan akun SSO kepada seluruh pegawai. Kedua, menunjuk satu tim sebagai percontohan.
Setelah itu fitur penulisan ke kipApp dan keluaran FRA kami kerjakan bertahap.
-->

---
layout: center
class: text-center
---

# Terima kasih

Kinetik: rencana sebelum kerja, rekap sesudah kerja, bukti untuk FRA.

<div class="mt-8 opacity-70 text-base">Pertanyaan dan masukan</div>

<!--
Terima kasih. Kami menunggu masukan dan arahan Bapak/Ibu pimpinan.
-->
