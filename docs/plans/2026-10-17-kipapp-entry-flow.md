# kipApp kegiatan entry flow ("Pelaksanaan Kinerja - Pelaksanaan"), for Kinetik

Date: 2026-10-17. Method: static reading of kipApp's public JavaScript only. No login, no API call.

## 0. Source and how to find it again

- kipApp main bundle: `https://kipapp.bps.go.id/9cee5c76c1d951a4cc86.main.js` (same hash as the earlier analysis). The page components are lazy chunks. Webpack builds a chunk URL as `<hash>.<chunkId>.js` (`__webpack_require__.u = id => map[id] + '.' + id + '.js'`, the map is `var _0x59e775` in the main bundle).
- Every chunk has its own string-array decoder (`aNNN_0x....`). A generic decoder is in `/tmp/kiptools/deob2.js` (extracts the array function, the decoder and the rotation IIFE, runs them in `vm`, replaces every aliased call). Decoded and beautified chunks are in `/tmp/kipjs/pretty/` (temporary. Rebuild with the steps above if `/tmp` is cleared).
- Component to module and chunk (module ids are webpack ids inside the chunk. The `__file` string is the original source path):

| Source file (`__file`) | Module id | Chunk file |
|---|---|---|
| `src/views/pages/pelaksanaan/aksi/Page.vue` (list page) | `0x12717` | `7667b6afecbc2cc267f6.3425.js` |
| `src/views/pages/pelaksanaan/aksi/ModalKirimKegiatan.vue` (send) | `0xdce6` | same chunk 3425 (also 6550) |
| `src/views/pages/pelaksanaan/aksi/ExportExcel.vue` (Download Excel) | `0x102cd` | chunk 3425 |
| `src/views/pages/home/DashboardKegiatanSayaBelumDikirim.vue`, `ModalKegiatanSayaBelumDikirim.vue` (badge on top of the page) | `0x7635`, `0x2c93` | chunk 3425 |
| `src/views/pages/pelaksanaan/aksi/ModalAdd.vue` (add) | `0x1c68` | `4575acd0a8d5b16359af.7272.js` |
| `src/views/pages/pelaksanaan/aksi/ModalAktivitas.vue` (presensi / SIMWAS picker) | `0x85fc` | chunk 7272 (also 5487) |
| `src/views/pages/pelaksanaan/aksi/ModalEdit.vue` (edit) | `0xc9ca` | `e7cc47ed7b0b9813b565.1658.js` |
| `src/views/pages/pelaksanaan/aksi/ModalCopy.vue` (copy) | `0x100dd` | `e02f063a12d7392eb534.5757.js` |
| `src/components/select/RencanaKinerjaSKPCustom.vue` (RK select inside the modals) | `0xb77d` | `a2be7188824a7b508936.75.js` |
| kegiatan API service (`/kegiatan*`) | `0x9b82` | chunk 75 (copies in 3528, 5487, 6550, 7705) |
| `src/components/select/RencanaKinerjaSKP.vue` (RK filter on the page), `SKP.vue`, `Tahun.vue`, `src/components/skp/Status.vue` | `0xdfd6` etc. | `315f242b75436ad5cb0f.6594.js` |
| SKP API service (`/skp*`) | `0x79cd` | `b3141fbec9aaa2f51534.1181.js` |
| dashboard API service (`/dashboard*`) | `0xdab5` | `bb83ff67bb7fd4d0aa1b.2333.js` |
| `src/views/pages/home/DashboardTambahKegiatanHariIni.vue` ("Tambah kegiatan hari ini" on Home) | `0x15678` | `375fcd20e2f76b4170d7.7672.js` |
| axios instance (base URL, `X-Auth`, error texts) | `0x2d11` | main bundle |
| route-to-role CRUD matrix | `0x11711` | main bundle |

UI kit: Ant Design Vue 1 (Vue 2), forms with `v-decorator` (async-validator rules). Dates use moment.

## 1. Route and screens

- Route path and name: `pelaksanaan-aksi` (`/#/pelaksanaan-aksi`). Menu: "Pelaksanaan Kinerja" > "Pelaksanaan". Page title "Pelaksanaan Kinerja - Pelaksanaan". Guide key `penggunaan-pelaksanaan-kinerja-pelaksanaan`.
- Deep link: `/pelaksanaan-aksi?q=<token>`. `token = rot13(Base64.encodeURI(qs.stringify(obj)))` with obj keys `periode, skp, wilayah, unitKerja, pegawai, pegawaiLabel, pegawaiNip, jenisPegawai` and optional `rk`. On mount the page decodes it, fills the filters and loads the list. The "Kegiatan Anda belum dikirim" modal uses this link ("Lihat").
- CRUD by role (route matrix, main bundle): role-0 (pegawai) and role-1: create, read, update, delete all true. role-2 and role-3: all false. Role-0 is restricted to self (`restrictSelf`: the Pegawai select shows only the user).
- Screens in this flow: list page. Modal "Add Capaian Kegiatan Perhari". Modal "Edit Capaian Kegiatan Perhari". Modal "Copy Capaian Kegiatan Perhari". Modal "List Aktivitas" (tabs "Presensi" and "SIMWAS"). Modal "Kirim kegiatan untuk dinilai". Confirm boxes for delete and batal. The Home page reuses the add modal through "Tambah kegiatan hari ini".
- Evidence: there is NO file upload anywhere in this flow. "Data Dukung" is a text field holding a URL (section 2.9). The list opens it with `window.open(datadukung, '_blank')`.

## 2. The add form (ModalAdd.vue), exact

Modal: title "Add Capaian Kegiatan Perhari", width 800, okText "Save", cancel button type danger (text is Ant's default "Cancel"), not closable / mask-closable / Esc while saving. Form layout horizontal, label column 6, wrapper 18, labels left. Props: `visible`, `data` (`selectedTahun`, `selectedPegawai`, `selectedSKP`, `idRK`), `isToday`.

Fields in order:

| # | Label | Control | Field key | Default | Rules (exact messages) |
|---|---|---|---|---|---|
| 1 | "Pegawai" | read-only: avatar (`foto-simpeg`) + `[niplama] nama` | none | from page | none |
| 2 | "Tahun" | read-only text | none | selected Tahun label | none |
| 3 | "SKP" | read-only text | none | selected SKP label, for example "<deskperiodeawal> - <deskperiodeakhir> (Triwulan <keterangan>)" | none |
| 4 | "Rencana Kinerja" | `select-rencana-kinerja-skp` (section 2.10) | `rencanaKinerja` | the page's RK filter (`idRK`) | required, "Pilih rencana kinerja" |
| 5 | (no label) | two independent checkboxes: "Gunakan periode tanggal" (`useRangeDate`), "Gunakan jam" (`useTime`) | none | both unchecked | none |
| 6 | "Tanggal" | `a-date-picker` placeholder "Pilih tanggal". Or, when "Gunakan periode tanggal", `a-range-picker` placeholders "Pilih tanggal awal" / "Pilih tanggal akhir". No clear button. "Today" shown | `tanggal` | empty. `isToday` sets today | required, "Pilih tanggal kegiatan" |
| 7 | "Jam Mulai" (only with "Gunakan jam") | `a-time-picker` placeholder "Pilih jam", format `HH:mm`, minuteStep 15, opens at 08:00 | `jamMulai` | empty | required, "Pilih jam mulai" |
| 8 | "Jam Selesai" (only with "Gunakan jam") | same, opens at 16:00, disabled until Jam Mulai has a value | `jamSelesai` | empty | required, "Pilih jam selesai". Validator: "Pilih jam mulai terlebih dahulu" if no start, "Pilih jam selesai harus setelah jam mulai" if end is before start (equal is allowed) |
| 9 | "Kegiatan" | textarea, 4 rows, placeholder "Deskripsi Kegiatan". A round primary button with icon `snippets` at bottom-right inside the textarea, tooltip "List Aktivitas", opens the presensi picker (section 4) | `kegiatan` | empty | required "Masukan Kegiatan". Whitespace "Kegiatan tidak boleh hanya spasi.". Min 10 "Kegiatan minimal 10 karakter." |
| 10 | "Progres" | `a-input-number` min 0, max 100, placeholder "Progres kegiatan", full width. Under it an `a-progress` bar (status "active" below 100, "success" at 100) | `progres` | **100** | required "Masukan progres kegiatan" |
| 11 | "Capaian" | textarea, 4 rows, placeholder "Deskripsi Capaian" | `capaian` | empty | required "Masukan Capaian". Whitespace "Capaian tidak boleh hanya spasi.". Min 10 "Capaian minimal 10 karakter." |
| 12 | "Data Dukung" | `a-input` (one line. `rows: 2` is set but ignored), placeholder "Link Data Dukung" | `dataDukung` | empty | type url "Format harus URL.". Optional ("Masukan URL." never fires). Whitespace "URL tidak boleh hanya spasi." |
| 13 | "Masukan ke capaian SKP" | checkbox | `isCapaianSKP` | unchecked | none |

No other helper text exists in the form.

### 2.1 Conditional fields

- The two checkboxes are independent. All four combinations are valid: single day. Date range. Single day + times. Date range + times.
- Toggling "Gunakan periode tanggal" keeps the chosen date: single `d` becomes `[d, d]`. A range becomes its first date.
- Payload rules (seen in code, `onOk`):
  - range off: `tanggal = d.format('YYYY-MM-DD')`, `tanggalselesai = null`.
  - range on: `tanggal = start`, `tanggalselesai = end`.
  - jam off: `jammulai = null`, `jamselesai = null`. jam on: both `HH:mm`.

### 2.2 Date limits (`disabledDate`, same code in Add, Edit, Copy)

Uses the selected SKP row (`skp.raw`):

- `jenisperiodepenilaian == 1` (monthly SKP): window = month `namaperiodepenilaian - 1` (0-based) of `tahun`.
- `jenisperiodepenilaian == 2` (quarterly SKP): window = months `3*(namaperiodepenilaian-1)` to `+2` of `tahun`, that is the quarter.
- A date is disabled if it is after the end of today, after the end of the window's last month, or before the first day of the window.

So: no future dates, and only dates inside the SKP period. The API itself accepts future dates (verified 2026-10-05, see the KB note). Only the form blocks them.

Calendar opening position (`defaultPickerValue`): `{year: tahun, month: namaperiodepenilaian - 1, day: isToday ? today : 1}`. For a quarterly SKP this opens at month 0..3 (Jan..Apr), not at the quarter. Inferred to be a kipApp bug. Do not copy.

### 2.3 Progres

Integer input 0..100, default 100, required, with a live progress bar. The add form is built for logging work already done (default 100).

### 2.4 Capaian

Free text, required, at least 10 non-space characters. It is a sentence, not a number. kipApp has no numeric capaian or satuan on a kegiatan.

### 2.5 Data Dukung (evidence)

A URL string, optional, checked as a URL by async-validator. Sent as `datadukung`. No upload endpoint is used by this flow. In the list: "Lihat" button (icon eye) opens it in a new tab. Empty shows italic "Tidak ada bukti dukung".

### 2.6 Masukan ke capaian SKP

Checkbox, sent as `iscapaianskp` 1 or 0. Default unchecked on Add. In the list it is the "Capaian SKP" column (green check). The "Kirim SKP untuk dinilai" step later refuses an SKP whose RK have no kegiatan marked here (section 5.1).

### 2.7 Save

`POST /kegiatan` with `skpid` = selected SKP id, `rkid`, `rencanakinerja` (only for a new RK, see 2.10), `kegiatan`, `tanggal`, `tanggalselesai`, `progres`, `jammulai`, `jamselesai`, `capaian`, `datadukung`, `iscapaianskp`, and `butirid`/`parentid` undefined (dropped by JSON). Success: notification "Berhasil" / "Kegiatan baru berhasil ditambahkan", form reset (progres back to 100), modal closes, page reloads the list, the RK filter list, and the badge. Failure: notification "Gagal" with the server `message`. The modal stays open.

### 2.8 Home shortcut "Tambah kegiatan hari ini"

Card or badge "Tambah kegiatan hari ini" (count "+"), only for role-0. On mount `GET /dashboard/skpbulanini?niplama=` gives `{skp, tahun}` (the current period SKP). Click: if no SKP, info "SKP periode ini belum ada" / "Silakan membuat SKP Periodik untuk periode ini terlebih dahulu." and redirect to `/perencanaan-bulanan`. Else opens ModalAdd with `isToday = true`, which pre-sets `tanggal` to today.

### 2.9 Validation display

Ant form shows the rule message under the field. Nothing is sent until all rules pass.

### 2.10 The RK select inside the modals (RencanaKinerjaSKPCustom.vue)

- Source: `GET /skp/rk?skpid=<skp>&direct=1`. Option value `rkid`, label `rencanakinerja`, group by `jeniskinerja`: group tag "Utama" (green, jenis 1) and "Tambahan" (orange, jenis 2). Searchable, placeholder "Pilih rencana kinerja SKP" ("Loading..." while loading).
- Under the select: "IKI Rencana Kinerja:" then a list of `iki` from `GET /skp/iki?rencanakinerjaid=<rkid>` (field `ikiid`, `iki`), or italic "Belum ada IKI". Error: "Gagal mengambil IKI rencana kinerja SKP".
- Dropdown footer button "Tambah rencana kinerja baru" opens modal "Tambah Rencana Kinerja" (okText "Save") with "Rencana Kinerja" textarea, placeholder "Deskripsi rencana kinerja", rules "Masukan rencana kinerja", "Rencana kinerja tidak boleh hanya spasi.", "Rencana kinerja minimal 10 karakter.". It adds a local option `value = 'new-<timestamp>'`, jenis 2, tag "RK Baru", removable ("Hapus"). On save of the kegiatan, a `new-` value is sent as `rkid = 0` plus `rencanakinerja = <text>`. Inferred: the server creates a Tambahan RK in the SKP (the page reloads the RK list after save. `onSuccessTambah` also reloads the SKP list when the response has `issync == 1`).

## 3. The list page (Page.vue)

### 3.1 Filters (top, label left / control right)

| Label | Control | Notes |
|---|---|---|
| "Pegawai" | `select-pegawai` | role-0 sees only self |
| "Tahun" | `select-tahun` | placeholder "Pilih tahun" |
| "Periode SKP" | `select-skp` with `jenis: 2` and `filterStatus: [1, 5, 6, 7]` | placeholder "Pilih SKP". Label "<deskperiodeawal> - <deskperiodeakhir> (Bulan|Triwulan <keteranganperiodepenilaian>)". Each option shows the SKP status tag (text from `statusskp`) |
| "Wilayah" | read-only text from SKP `namawilayah` | |
| "Unit Kerja" | read-only text from SKP `namaunitkerja` | |
| "Rencana Kinerja" | `select-rencana-kinerja-skp` (RencanaKinerjaSKP.vue), allow clear | optional. Also shows an info box "Status RK:" with tag "Selesai" (green) or "Belum Selesai" (red) from `tglselesai`, and an "Edit Status" button (hidden when locked) opening "Edit Status Rencana Kinerja" with checkbox "Selesai" -> `PUT /skp/rk {id, skpid, jenis, rencanakinerja, lingkupid, isselesai}`, success "Status Rencana Kinerja SKP berhasil diedit" |

The list loads only when an SKP is chosen (`filterSelected = skp`). Changing Pegawai or Tahun clears SKP, RK and the table. Changing the RK filter reloads with `rencanakinerjaid`.

There is no status filter and no date filter. The "status" is visible only as the "Dikirim" column.

### 3.2 Toolbar

- Right: "Download Excel" (icon file-excel, disabled without SKP) and "Add" (icon plus, shown only if `crud.create` and the SKP is not locked, disabled without SKP).
- Left: "Refresh" (reloads list and badge) and a search box, placeholder "Pencarian". Search is client side, case-insensitive substring over `kegiatan`, `capaian`, `tanggal`, and `jammulai`/`jamselesai` when present.
- Right: bulk dropdown button "Aksi kegiatan terpilih (<n>)" with items "Kirim untuk dinilai" (green check icon) and "Batalkan pengiriman" (pink close icon). Disabled when nothing is selected or the SKP status is not 1.
- Above everything: the badge "Kegiatan Anda belum dikirim" (count from `GET /dashboard/kegiatanpegawai/belumkirim?niplama=`, response `[{jumlahkegiatan, kegiatan: [...]}]`). Click opens "Kegiatan Anda yang belum dikirim": columns "Tahun", "Bulan", "Jumlah", and "Lihat" (deep link, section 1).

### 3.3 Table

Ant table, small, fixed layout, scroll x 1100 y 500, row key `kegiatanperhariid`, pagination at bottom with size changer, total text "<a>-<b> dari <total> data". Checkbox selection column first.

| Title | Key | Content | Sort |
|---|---|---|---|
| "No" (fixed left, 60px) | `no` | row number after search filter | numeric |
| "Tanggal" (120px) | `tanggal` | `tanggal`, then "- tanggalselesai" if set. Next line "(jammulai - jamselesai)" if both set | by `tanggal + jammulai` timestamp. **default sort descending** |
| "Rencana Kinerja" | `rencanakinerja` | text | string |
| "Kegiatan" | `kegiatan` | text | string |
| "Progress" (100px) | `progres` | small progress bar, "success" at 100 | numeric |
| "Capaian" | `capaian` | text | string |
| "Bukti Dukung" (100px) | `buktiDukung` | "Lihat" button or italic "Tidak ada bukti dukung" | none |
| "Capaian SKP" (100px) | `capaianSKP` | green check if `iscapaianskp == 1` | numeric |
| "Dikirim" (150px) | `dikirim` | green check + `tanggalkirim` when not null | string |
| "" (90px) | `aksi` | dropdown button "Aksi" (icon down-circle) | none |

The `aksi` column is hidden unless the role can update or delete and the SKP is not locked.

Row actions (dropdown, placement bottomRight):

- "Edit" (edit icon): if `crud.update` and not locked. Shown for sent rows too (the edit modal then makes almost everything read-only, section 3.5).
- "Copy Kegiatan" (copy icon): if `crud.create` and not locked.
- "Delete" (red close-circle icon): if `crud.delete`, not locked, and `tanggalkirim === null`.

### 3.4 Status values and how they are derived

Per kegiatan, the page knows only one state, from `tanggalkirim`:

| Shown | Rule |
|---|---|
| belum dikirim (no mark in "Dikirim") | `tanggalkirim === null` |
| dikirim (green check + date) | `tanggalkirim !== null` |

There is no "dinilai" state on this screen. `GET /kegiatan` has a `flagdinilai` parameter but no fetched caller passes it, and no row field about rating is read. Rating happens on the atasan's penilaian screens.

Per SKP (`statusskpid` of the chosen SKP) the page derives two locks:

| Name in code | Rule | Effect |
|---|---|---|
| `lockSKP` | true unless `statusskpid` is 1 or 6 | hides Add, Edit, Copy, Delete, the Aksi column and "Edit Status" |
| `skpSedangDibuat` | `statusskpid === 1` | row checkboxes and the bulk send/cancel menu work only here |

Status 1 is "Sedang dibuat" (seen in live data, KB note). The meaning of 5, 6, 7 is not in the code (the label text comes from the server field `statusskp`). Inferred: 5 = sent for rating (the send-SKP call uses `flag: 5`), 6 = returned to the employee (edits allowed again, sending not), 7 = rated.

### 3.5 Edit (ModalEdit.vue)

Title "Edit Capaian Kegiatan Perhari", same layout and rules as Add. Prefill from the row: RK = `rkid`, `tanggal`/`tanggalselesai` (range on when both exist), `jammulai`/`jamselesai` (jam on when both exist), `kegiatan`, `progres`, `capaian`, `datadukung`, `iscapaianskp == 1`.

If the row is sent (`tanggalkirim !== null`, `isDikirim`): RK, Tanggal, Jam, Kegiatan, Progres, Capaian and Data Dukung show as plain text ("Tidak ada bukti dukung" when empty). Only "Masukan ke capaian SKP" stays editable. Save then sends the stored values plus the new `iscapaianskp`.

Save: `PUT /kegiatan` with `id = kegiatanperhariid` and the same fields as POST (no `parentid`). Success "Berhasil" / "Kegiatan berhasil diedit". Failure "Gagal" + server message.

### 3.6 Copy (ModalCopy.vue)

Title "Copy Capaian Kegiatan Perhari". Same form, every field prefilled from the source row (RK, dates, times, kegiatan, progres, capaian, datadukung, iscapaianskp). Save: `POST /kegiatan` plus `parentid = <source kegiatanperhariid>`. Success "Berhasil" / "Kegiatan berhasil ditambahkan".

### 3.7 Delete

Confirm: title "Apakah akan menghapus kegiatan ini?", okText "Yes" (danger), cancelText "No" (primary), focus on cancel. Call `DELETE /kegiatan` (see 6). Success "Berhasil" / "Kegiatan berhasil dihapus", reload list and badge. Failure "Gagal" + message.

### 3.8 Download Excel

A hidden table, file "CKP <timestamp>.xlsx", sheet "CKP". Header "Capaian Kinerja Perhari (CKP)", then rows "Pegawai", "Tahun", "Periode SKP", "Wilayah", "Unit Kerja". Columns "Tanggal Mulai", "Tanggal Selesai" (= tanggal when empty), "Jam Mulai", "Jam Selesai", "Rencana Kinerja", "Kegiatan", "Progres (%)", "Capaian", "Data Dukung" (hyperlink), "Capaian SKP" ("Ya"/"Tidak"). Sorted newest first.

## 4. Presensi flow (ModalAktivitas.vue)

Entry: the round `snippets` button inside the "Kegiatan" textarea (tooltip "List Aktivitas") in Add and Edit (and Copy). It is not a separate page or bulk import. It fills one form.

1. Modal "List Aktivitas", width 600, no footer. Tabs: "Presensi" (always) and "SIMWAS" (only when the SKP's `unitentityid` is one of '8', '31', '32', '33', '39'. These are Inspektorat units).
2. On open: `GET /kegiatan/presensi?periodeid=<skp.periodeid>&periodepenilaianid=<skp.periodepenilaianid>&niplama=<pegawai niplama>`. Error: "Gagal mengambil aktivitas presensi" + message.
3. Info alert: "Klik tombol tambah atau double klik pada baris untuk menggunakan aktivitas".
4. Table (small, 500x500 scroll, row key `id`): "Tanggal" (`fullDate`, sorted ascending by default), "Deskripsi" (`description`), and a round "+" button. Response fields read by the code: `id`, `fullDate` (YYYY-MM-DD), `time` (YYYY-MM-DD HH:mm, used only for an unused sort index), `description`. The live probe (KB) also saw `tanggal`, `jam`, `menit`.
5. The user clicks "+" or double-clicks a row. The modal closes and the form gets:
   - `kegiatan` := row `description` (replaces what was typed).
   - `tanggal` := row `fullDate` as a single date. "Gunakan periode tanggal" is switched off.
   - Nothing else is prefilled (no jam, no progres, no capaian, no RK).
6. The user still completes RK, capaian and the rest and presses "Save". One presensi row gives one kegiatan. There is no multi-select.

SIMWAS tab (Inspektorat only): `GET /kegiatan/simwas` (same params) lists `start_date`, `end_date`, `kegiatan`. A "Sync" button (enabled when `issync != 1`) asks "Apakah Anda yakin? Anda hanya dapat melakukan sinkronisasi aktivitas dengan SIMWAS satu kali setiap bulan." and calls `POST /kegiatan/simwas {niplama, periodeid, periodepenilaianid, skpid}`. Success "Aktivitas berhasil ditambahkan". Not relevant to BPS Sulteng.

## 5. Send ("kirim") and cancel ("batal")

### 5.1 Send

Preconditions in the UI: SKP status 1 ("Sedang dibuat"), at least one row selected. The client does not check progres, capaian, or whether a row is already sent (sent rows can be selected too. What the server does with them is unknown).

1. "Aksi kegiatan terpilih (n)" > "Kirim untuk dinilai" opens modal "Kirim kegiatan untuk dinilai", okText "Yes", cancelText "No" (danger), locked while saving.
2. Content: warning alert, bold "Apakah Anda yakin mengirim kegiatan ini untuk dinilai?", description "Tindakan ini tidak dapat dibatalkan". Then an info alert with checkbox, bold "Kirim SKP untuk dinilai" followed by " (Centang checkbox ini ketika SKP Anda sudah siap dikirim untuk dinilai atasan Anda)". Default unchecked.
3. On "Yes", in order:
   1. If "Kirim SKP untuk dinilai" is checked: `GET /skp/capaian?skpid=&direct=1&penilaian=1&ispegawai=1`. Errors thrown before anything is sent: "SKP tidak ditemukan" (no SKP id), "Detail SKP tidak ditemukan" (not exactly one row), "SKP belum memiliki rencana kinerja atau belum ada kegiatan yang dimasukkan ke dalam capaian SKP" (row has no `rks`).
   2. "Tidak ada kegiatan yang dipilih" if the list is empty. "Kegiatan belum dipilih" if missing.
   3. `POST /kegiatan/kirim` body `{"id": [<kegiatanperhariid>, ...]}` (all selected ids in one call).
   4. If the checkbox is checked: `POST /skp/penetapan/kirim {skpid, flag: 5}`.
4. Success: modal closes, selection cleared, list reloaded, SKP list reloaded when the SKP was sent too. There is no success toast in this modal.
5. Any error (client check or server `status: false`): notification "Gagal" with the message. The modal stays open.

### 5.2 Cancel ("Batalkan pengiriman")

1. No selection: notification "Gagal" / "Tidak ada kegiatan yang dipilih".
2. Confirm: "Apakah Anda yakin membatalkan pengiriman dari kegiatan yang dipilih?", "Yes" (danger) / "No", focus on No.
3. `POST /kegiatan/batal` body `{"id": [<ids>]}`.
4. Success "Berhasil" / "Kegiatan berhasil dikembalikan", list reloaded. Failure "Gagal" + message.

Note the modal text says sending "tidak dapat dibatalkan", yet "Batalkan pengiriman" exists. The client allows cancel only while the SKP is in status 1. Whether the server refuses after rating is unknown.

### 5.3 How rejections show

Every service call throws when the body has `status === false`, using the server `message`. The axios instance adds: no response -> "Terjadi kesalahan, silahkan cek koneksi internet, VPN, atau coba logout lalu login kembali.". HTTP 401 -> "Sesi habis. Anda akan diarahkan untuk login kembali." and redirect to `/auth/logout` after 3 s. Other HTTP errors -> "Ooops.. terjadi kesalahan server.". No token -> "Sesi habis silahkan reload halaman ini untuk login kembali". All of these surface as an Ant notification titled "Gagal".

## 6. API calls (all "seen in code" unless marked)

Base `https://kipapp.bps.go.id/api/v1/`, header `X-Auth: Bearer <JWT>`, timeout 60 s. The axios default content type is `application/x-www-form-urlencoded`, but `transformRequest` always `JSON.stringify`s the body (FormData passes through). Writes set `Content-Type: application/json`. Success responses are returned whole (`response.data`). List endpoints return a bare JSON array.

| Method and path | Sent | Response fields the UI reads |
|---|---|---|
| `GET /kegiatan` | params `skpid`, `rencanakinerjaid` (optional), `flagdinilai` (not passed here) | array of `kegiatanperhariid, tanggal, tanggalselesai, jammulai, jamselesai, rkid, rencanakinerja, kegiatan, progres, capaian, datadukung, iscapaianskp, tanggalkirim` |
| `POST /kegiatan` | JSON `skpid, rkid, rencanakinerja, kegiatan, tanggal, tanggalselesai, progres, butirid, jammulai, jamselesai, capaian, datadukung, iscapaianskp, parentid` | only `status`/`message` (plus `issync` on the SIMWAS path). No id (KB: list again to find it) |
| `PUT /kegiatan` | JSON `id` + same fields, no `parentid` | `status`/`message` |
| `DELETE /kegiatan` | axios `{data: qs.stringify({id})}`, that is the body is the string `id=<n>` passed through `JSON.stringify` | `status`/`message`. KB records that a live body `{"id": n}` works. The exact wire format of kipApp's own call is inferred |
| `POST /kegiatan/kirim` | JSON `{id: [ids]}` | `status`/`message` |
| `POST /kegiatan/batal` | JSON `{id: [ids]}` | `status`/`message` |
| `GET /kegiatan/presensi` | params `periodeid, periodepenilaianid, niplama` | array of `id, fullDate, time, description` |
| `GET /kegiatan/simwas`, `POST /kegiatan/simwas` | params or JSON `niplama, periodeid, periodepenilaianid` (+ `skpid` on POST) | `start_date, end_date, kegiatan`. POST result `issync` |
| `GET /skp` | params `skpid, niplama, periodeid, pegawaiid, jenis, skpidtahunan` (the page sends periode, pegawai, jenis 2) | `id, statusskpid, statusskp, tahun, periodeid, periodepenilaianid, jenisperiodepenilaian, namaperiodepenilaian, keteranganperiodepenilaian, deskperiodeawal, deskperiodeakhir, periodeawal, unitkerjaid, unitentityid, namawilayah, namaunitkerja, issync` |
| `GET /skp/rk` | params `skpid, direct=1` | `rkid, rencanakinerja, jeniskinerja, tglselesai` |
| `PUT /skp/rk` | `qs.stringify({id, skpid, jenis, rencanakinerja, lingkupid, isselesai})` | `status`/`message` |
| `GET /skp/iki` | params `skpid, rencanakinerjaid, direct` | `ikiid, iki` |
| `GET /skp/capaian` | params `skpid, direct, penilaian, ispegawai` | array, first row `rks` |
| `POST /skp/penetapan/kirim` | JSON `skpid, flag` (5 here) | `status`/`message` |
| `GET /dashboard/kegiatanpegawai/belumkirim` | param `niplama` | `[ {jumlahkegiatan, kegiatan: [{tahun, bulan, jumlahkegiatan, periodeid, periodepenilaianid, wilayahid, unitkerjaid, skpid, wilayah, unitkerja}]} ]` |
| `GET /dashboard/skpbulanini` | param `niplama` | `{skp, tahun}` (select-shaped objects) |

## 7. Mapping to Kinetik

Note: the brief names `resources/js/Components/Kinetik/PlanFormDialog.vue`. That file does not exist. Kinetik's kegiatan form is `resources/js/Components/Kinetik/PlanItemFormDialog.vue`. `resources/js/Components/PerformancePlans/PlanFormDialog.vue` is the RK form. This section uses PlanItemFormDialog, PlanItemDetailDialog, PlanItemTable, `RekapMingguan.vue`, `PlanItemController`, `PushPlanItemAction`, `CompletePlanItemAction`, `SendPlanItemAction`, `ApiKipWriter`, and the `plan_items` table.

### 7.1 Already matches

- Field set: RK, kegiatan (`description`), date range or single day with clock time, progres 0..100 (`progress`), uraian capaian (`capaian_note` -> `capaian`), bukti dukung as an optional URL (`evidence_url` -> `datadukung`, rule `url`). Kinetik has no upload either.
- The labels "Gunakan periode tanggal" and "Gunakan jam" and default times 08:00 / 16:00.
- Jam selesai must be after jam mulai (`after:time_start`. KipApp also allows equal times).
- Send and cancel use the same calls: `POST /kegiatan/kirim` and `/kegiatan/batal` with `{id: [..]}`.
- Status from `tanggalkirim`: Kinetik's `kip_sent_at` (copied from kipApp by `ReconcilePlanItemsAction`) is the same "dikirim" fact.
- kipApp errors reach the user as a message (`back()->with('error', ...)`), like kipApp's "Gagal" notification.
- A missing quarter SKP stops the push with a clear message, like kipApp's "SKP periode ini belum ada".

### 7.2 Differs

| Topic | kipApp | Kinetik now |
|---|---|---|
| Purpose of the form | log done work: progres default 100, capaian text required | plan first: progres default 0, capaian optional, pushed on the start date |
| Date limits | no future dates. Only inside the SKP period (month or quarter) | any date. Future allowed. A range can cross quarters |
| Range and time | two independent checkboxes. Range + time allowed | radio: range OR single day with time |
| Empty range / time in payload | `tanggalselesai = null`, `jammulai/jamselesai = null` | always sends `tanggalselesai`. `ApiKipWriter` fills `08:00`/`16:00` when null, so every Kinetik kegiatan shows times in kipApp |
| Kegiatan text | required, not only spaces, min 10 | required, max 1000 |
| Capaian text | required, min 10 | optional. Empty `""` is pushed |
| Masukan ke capaian SKP | checkbox, default off | not shown. Always `iscapaianskp = 1` |
| Edit an unsent kegiatan already in kipApp | full edit, `PUT /kegiatan` | blocked once pushed (`status === 'planned'` check). Only the result fields can change, and only "Tandai selesai" writes them |
| Edit a sent kegiatan | only "Masukan ke capaian SKP" | blocked |
| Delete | `DELETE /kegiatan` while unsent | "Batalkan" only while `planned`. Nothing is deleted in kipApp |
| Copy | "Copy Kegiatan", `parentid` | none |
| Presensi | "List Aktivitas" picker fills kegiatan and tanggal | none |
| Send | bulk, one call, confirm text, optional "Kirim SKP untuk dinilai", only while SKP status 1 | one item at a time, no confirm, no SKP status check, behind `kinetik.kip.send_to_atasan` |
| Cancel send | bulk, confirm text | one item, no confirm |
| SKP lock | Add/Edit/Copy/Delete hidden unless SKP status 1 or 6 | Kinetik does not read `statusskpid` |
| New RK on the fly | "Tambah rencana kinerja baru" sends `rkid 0` + text | RK only from Kinetik planning |
| List | per SKP, RK filter, search on kegiatan/capaian/tanggal/jam, sort, "Dikirim" date column | per week (last week, this week), search on kegiatan/RK, stage column |

### 7.3 Changes to make (concrete)

Keep Kinetik's decisions: numeric target and capaian with one satuan. The PJ writes one paraphrase, target and capaian per RK. No claim flow. These changes touch only the member's kegiatan entry.

1. **Two checkboxes instead of the radio** in `PlanItemFormDialog.vue`: "Gunakan periode tanggal" and "Gunakan jam", independent. Replace `mode` with `use_range` and `use_time`. Controller: drop the `same:date_start` rule for timed rows. Keep `after:time_start`. Add `step="900"` to the time inputs (15 minutes).
2. **Send null like kipApp.** In `PushPlanItemAction` send `tanggalselesai = null` when the row is one day, and `jammulai/jamselesai = null` when no time. In `ApiKipWriter::payload()` stop defaulting to `08:00`/`16:00`. `CompletePlanItemAction` must pass the row's own null values back instead of `?? '08:00'`. Check first on a throwaway kegiatan that `PUT /kegiatan` accepts null times. kipApp's own form sends null, so it probably does.
3. **Date limits.** Validate that `date_start` and `date_end` fall in one quarter of `kinetik.kip.tahun` (one SKP). Keep future dates for planning, but follow kipApp's "no future date" rule for the write: disable "Kirim ke kipApp" (`plan-items.push`) and refuse it in the controller when `date_start` is after today ("Kegiatan belum bisa dikirim sebelum tanggal mulainya."). The scheduler already pushes on the start date.
4. **Text rules.** `description`: required, not only spaces, min 10, messages "Kegiatan tidak boleh hanya spasi." and "Kegiatan minimal 10 karakter.". `capaian_note`: optional while planning, but required with min 10 on "Tandai selesai" (`complete`), messages "Masukan Capaian" and "Capaian minimal 10 karakter.". This keeps kipApp's rule at the moment Kinetik writes the capaian to kipApp.
5. **Progres default.** Keep 0 for a planned kegiatan. When the form is opened for a date before today (logging past work), default progres to 100, like kipApp.
6. **"Masukan ke capaian SKP".** Add the checkbox to the form, store it (`plan_items.is_capaian_skp`, boolean, default true to keep today's behaviour), and pass it to `iscapaianskp`. Decide if the default follows kipApp (off). It must stay on for at least one kegiatan per RK, or kipApp refuses "Kirim SKP untuk dinilai".
7. **Edit after push, before send.** Allow `PlanItemController@update` when `status` is `pushed`, `in_progress` or `done` and `kip_sent_at` is null. After saving, call `ApiKipWriter::updateActivity` with the full row (like `CompletePlanItemAction`). When `kip_sent_at` is set, allow only the `is_capaian_skp` change, like kipApp.
8. **Delete in kipApp.** Allow "Batalkan" while unsent even after push: call `DELETE /kegiatan` with the `kegiatanperhariid`, then set `status = 'cancelled'` (keep the row for the Friday evaluation). Confirm text: "Apakah akan menghapus kegiatan ini?".
9. **Copy.** Add "Salin kegiatan" to `PlanItemDetailDialog`: open the form prefilled from the row, with `parent_id` stored on the new plan item and sent as `parentid` when pushed.
10. **Presensi picker.** Add a "List Aktivitas" button next to the Kegiatan field. A new endpoint in Kinetik calls `GET /kegiatan/presensi` with the member's token, `periodeid = kinetik.kip.periode_id`, `periodepenilaianid = quarter of the form date`, and `niplama`. Show "Tanggal" and "Deskripsi" sorted by date, with the alert text "Klik tombol tambah atau double klik pada baris untuk menggunakan aktivitas". Picking a row sets `description` and a single `date_start = date_end = fullDate` and turns off "Gunakan periode tanggal". Do not prefill anything else. Use Kinetik's searchable dialog pattern, not a native select.
11. **Send with confirmation and in bulk.** In `RekapMingguan.vue`, add row checkboxes to `PlanItemTable` for rows that are in kipApp and not sent, plus "Kirim ke atasan (n)" and "Batalkan kirim (n)". `SendPlanItemAction` takes a list and makes one `POST /kegiatan/kirim` call. Confirm with kipApp's text: title "Kirim kegiatan untuk dinilai", "Apakah Anda yakin mengirim kegiatan ini untuk dinilai?". For cancel: "Apakah Anda yakin membatalkan pengiriman dari kegiatan yang dipilih?", success "Kegiatan berhasil dikembalikan". Do not add "Kirim SKP untuk dinilai" yet: it submits the official SKP and is still untested (handoff doc).
12. **SKP status lock.** `PushPlanItemAction::skpAndRk` already loads the quarter SKP. Store `statusskpid` (and `statusskp` text) per member and quarter. Hide send/cancel unless the status is 1, and hide edit/delete unless it is 1 or 6, with a short reason ("SKP triwulan sudah dikirim untuk dinilai.").
13. **List details.** In `PlanItemTable`, show the "Dikirim" date (from `kip_sent_at`) and a "Lihat" link for `evidence_url` or "Tidak ada bukti dukung". Extend the search to `capaian_note` and the dates. Keep the weekly layout: it is Kinetik's own decision.
14. Do not copy "Tambah rencana kinerja baru" (`rkid 0`). RK stay owned by Kinetik planning.

## 8. Open questions (not answerable from code)

1. Exact meaning of SKP status ids 5, 6 and 7 (the text comes from `statusskp`).
2. Does `POST /kegiatan/kirim` accept rows that are already sent, or rows with progres below 100 or empty capaian? The client checks none of these.
3. Does `POST /kegiatan/batal` work after the atasan rates the kegiatan, or after the SKP left status 1?
4. What exactly does the server do with `rkid = 0` + `rencanakinerja` (creates a Tambahan RK in the SKP?) and when does it return `issync`?
5. Does `PUT /kegiatan` accept `jammulai/jamselesai = null` for a row that had times (kipApp's form sends null, but Kinetik has only tested with times)?
6. What is `butirid` for? kipApp's form never sets it.
7. The wire format of kipApp's own `DELETE /kegiatan` (qs-encoded string through `JSON.stringify`) versus the `{"id": n}` body that Kinetik verified.
8. Full field list of `GET /kegiatan/presensi` (code reads `id, fullDate, time, description`. The live probe saw `tanggal, jam, menit, description`).
