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
