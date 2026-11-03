# e-Kinerja BKN (kinerja.bkn.go.id) API endpoint inventory

Read from the shipped Vue SPA bundle (`/js/app.js`, public page) on 2026-11-02. Static string literals, no authenticated calls made; one unauthenticated `GET /api/skp` returned 401. 222 distinct `/api/...` paths found, 93 outside `/api/admin/*`.

Auth: `Authorization: Bearer <token>` from `localStorage.getItem('token')`, set by Keycloak OIDC (realm `public-siasn`, client `si-kinerja`, same IdP as MyASN).

## Member-facing (93), grouped by topic

- **SKP / RHK:** `/api/skp`, `/api/skp/`, `/api/skp_awal`, `/api/skp/cetak_download/`, `/api/skp/cetak_status/`, `/api/rhk/`, `/api/pohon_rhk/`, `/api/indikator/`
- **Kegiatan harian:** `/api/daily_activity` (+ `/harian`, `/konteks`, `/list_skp`, `/list_skp_harian`, `/cek_kemarin`, `/check/`, `/renaksi/`, `/is_enable_kinerja_harian`)
- **Tim / unit:** `/api/tim_kerja`, `/api/unor/` (+ `cari_unor*`)
- **Pegawai / profil:** `/api/pegawai` (+ `detil`, `detil_profil`, `profil`, `profil_awal`, `profil_singkat`, `ringkas`, `unor`, `nik`, `is_kadis`, `cuti`, `cuti_bawahan`)
- **Angka kredit:** `/api/ak`, `/api/ak/2022`, `/api/ak/perhitungan`, `/api/ak/setting`, `/api/ak/skp`, `/api/ak_bawahan/*`
- **Penilaian 360:** `/api/penilaian_360/*`
- **Dasbor:** `/api/dasbor/*`, `/api/dashboard/daily_activity*`
- **Sinkronisasi dengan SIASN/PMM:** `/api/sinkro_pmm/*`, `/api/sinkro_siasn/*` (jabatan, profil, angka_kredit, penilaian)
- **Export:** `/api/export_excel/download/`, `/api/export_excel/status/`

Full list (all 222, including `/api/admin/*`): `/tmp/myasn/endpoints.json` on this machine (not committed — regenerate from the public bundle if needed; delete after use, it's scratch).

## Relevance to Kinetik

Mapping to our model: RHK ~ RK, tim_kerja ~ tim, daily_activity ~ kegiatan/PlanItem, skp ~ SKP. If/when BKN grants API access, `daily_activity`, `rhk/`, `skp` and `tim_kerja` are the ones a sync adapter would call. `sinkro_pmm`/`sinkro_siasn` suggest e-Kinerja itself already does cross-system sync jobs — worth asking BKN whether a similar sync contract could be opened to partner apps instead of a raw API grant.

## Does e-Kinerja have "projek"? (checked 2026-11-03)

No. The whole `kinerja.bkn.go.id` bundle (4.5 MB) has no `proyek`, `projek` or `project` entity (the only "project" hit is a PDF library line-cap constant), and none of the 222 API paths mention it. Its model is SKP > RHK (rencana hasil kerja, linked to the superior's RHK, see `pohon_rhk` and `rhk_atasan`) > indikator, plus `tim_kerja`, `renaksi` (rencana aksi, 399 mentions) and `daily_activity`. So Kinetik's projek level is a kipApp concept. For the e-Kinerja era the grouping that carries over is team and the RHK tree (RK to RK Ketua), not projek. Consequence: keep projek optional in new features and do not build more rules on it.

## Hierarchy, activity and IKU/IKI compatibility (2026-11-03)

kipApp (API rows + scraped sample): Perjanjian Kinerja (tujuan > sasaran > IKU: `indikatorid`, `kode`, `satuan`, `tahun1..5`) > tim kerja > RK Ketua > projek > RK Anggota (`rkproyekid`). Per pegawai: SKP tahunan > SKP triwulan (copied, `iscopied`) > RK SKP (`rkid`, `timkerjaid`, `rencanakinerjaatasan` text) > IKI (`ikiid`, target embedded in text) and kegiatan (`kegiatanperhariid`).

e-Kinerja (bundle): RHK organisasi (+ indikator organisasi, PKPN flag) and RHK individu linked by `rhk_atasan`; tim_kerja and SKP; indikator (`deskripsi`, `target`, `perspektif`, `aspek` for jabatan fungsional) and rencana aksi (`renaksi`) under the RHK; `daily_activity` (`rhk_id`, `renaksi_id`, `indikator_id`, `skp_id`, `kegiatan`, `date`, `start`, `end`, `realisasi` + `satuan`, `is_selesai`, `eviden`, `bukti_eviden`, `is_cuti`, `sumber`, `ref_kegiatan_id`).

Mapping: kegiatan to daily_activity (text, time, evidence map directly; result differs: numeric realisasi + satuan and a done flag instead of 0-100 progres). IKU ~ indikator organisasi, IKI ~ indikator individu (target is its own field there, so the target-text parser would not be needed). Unverified: allowed values of `perspektif`/`aspek`, and whether `renaksi` is a plan or a realisation.

## Correction (2026-11-03): there IS an ID bridge between RK Anggota and the SKP

`GET v1/skp/rk/copy` (the "Copy Rencana Kinerja" list a member uses to put RK into the SKP) returns rows `{id, rencanakinerja, timkerja, iscopied, isused}` where `id` is the **rkproyekid** of `rk/proyek` (14 of 14 matched for NIP 340060924). So a member's own RK has an exact projek: join `rk/proyek` rows on the member's `niplama` and the RK text. Stored on `employee_rks.project_id` by the structure sync; 1,122 of 1,247 member RK got a projek on prod (0 before). The earlier statement that no joining ID exists was wrong: it holds only between `skp/rk` (`rkid`) and `rk/proyek`, not for the copy endpoint. `GET v1/rk/master` returned no rows for this unit; `POST v1/rk/proyek/copy` takes `rkmid` (RK master) but is write-only for us.
