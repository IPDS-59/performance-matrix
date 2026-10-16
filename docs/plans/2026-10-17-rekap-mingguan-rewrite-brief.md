# Brief: rewrite Rekap Mingguan as one page (phase 1, members)

Branch: `feature/rekap-mingguan-rewrite` (from `feature/demo-kegiatan-page`). Do not touch `develop` or `main`. Do not deploy. Never run the dev server on port 8000 or 8091. For a visual check, use a throwaway SQLite file and `php -S 127.0.0.1:8092` from `public/` (see the end), then delete both.

Read first: `docs/plans/2026-10-12-kipapp-planning-flow-handoff.md` (section "Planning redesign: decisions of 2026-10-16"), `docs/prototype/kinetik-rencana-rekap.html`, and `resources/js/Pages/Kinetik/DemoKegiatan.vue` (the target look, built with the app's own components). Product text is Indonesian. Code identifiers are English. Dropdowns use the searchable `Select`. Forms are popups through `resources/js/Components/FormDialog.vue`. Types live outside `.vue` files. Logic goes in actions and composables. Tests ship in the same commit as the logic.

## Goal

One page called **Rekap Mingguan** replaces both the member claim page and "Rencana Minggu Ini". The member plans and monitors in one place. There is no claim flow for members.

## Requirements (phase 1)

1. Route `weekly.index` (`/rekap-mingguan`) shows the signed-in member only: table "Capaian minggu lalu" and table "Rencana minggu ini", week picker, search, "Sinkronkan data saya", and a "Tambah" button. Rows are `PlanItem` records. Columns are short (kegiatan, RK, tanggal, target, capaian, status). A row click opens a popup with every field and the next action (Kirim ke kipApp, Tandai selesai, Kirim ke atasan, the last one only when `kinetik.kip.send_to_atasan` is on).
2. Add and edit are popups. The add form matches kipApp: RK (searchable, from the member's RK), kegiatan, "periode tanggal" or "jam" choice, dates or time, progres, bukti dukung link, uraian capaian (text sent to kipApp), plus Kinetik fields **target (number)**, **satuan** and **capaian (number)**. Target and capaian are numbers, never text. Show capaian against target with a percentage.
3. Migration: add `capaian` (decimal, nullable), `capaian_note` (text, nullable), `evidence_url` (string, nullable), `progress` (unsigned tiny integer, default 0), `time_start` and `time_end` (time, nullable) to `plan_items`. Keep `target` and `target_unit`. `CompletePlanItemAction` and the push action send `uraian` as kipApp `capaian`, the link as `datadukung`, and `progress`. Keep every kipApp write the same as today otherwise.
4. Retire the member claim page: remove `Pages/Kinetik/WeeklyScrapper.vue` and the member-facing claim routes and tests. **Do not delete the claim tables, models or anything the team recap still reads** (`TeamRecapController`, `RecapAggregator`, monthly recap). Phase 2 rewrites those. If removing a route breaks the team recap, keep the code and only remove the member page.
5. Menu (`resources/js/Layouts/AppLayout.vue`): remove "Rencana Minggu Ini", keep one "Rekap Mingguan" entry. The route `weekly-plan.index` redirects to `weekly.index`. Update `resources/js/data/guide.ts` (Buku Pedoman) to the new flow, without mentioning claims.
6. Own card only: a PJ also sees only their own rows on this page.
7. Cycle: week 1 meeting enters targets for the next week. Later meetings review last week's capaian, then plan the current week. The "Capaian minggu lalu" table lets the member edit capaian, uraian and evidence for last week's rows.
8. Replace the old message "Admin menjalankan Sinkronisasi Kegiatan" with a pointer to "Sinkronkan data saya". Run the first sync automatically after a member connects SSO (in `ConnectKipAccountAction` or the OTP and connect controllers; queue it or run it best-effort so a sync failure never blocks login).
9. Delete `DemoKegiatan.vue` and its local-only route when the real page replaces it.

## Out of scope (phase 2, do not start)

PJ recap rewrite (context panel, parafrase, RK target and capaian), monthly recap, removing the claim tables, converting the CRUD create and edit pages to popups.

## Done when

`php artisan test`, `vendor/bin/pint --test`, `pnpm exec vue-tsc --noEmit`, `pnpm exec vitest run` and `pnpm exec vite build` all pass. New tests cover: the page shows only the signed-in member, add and edit validation (numbers, date order), the new columns reaching kipApp payloads, the redirect, and the post-connect sync. Commit in small steps with messages in the repo style, no attribution lines, never commit `docs/credentials` or `docs/kinetik/`. Finish by writing a short summary of what changed and what is left in `docs/plans/2026-10-17-rekap-mingguan-rewrite-status.md`.

## Visual check (optional)

```
export DB_CONNECTION=sqlite DB_DATABASE=/tmp/rekap-demo.sqlite APP_ENV=local
touch $DB_DATABASE && php artisan migrate --force
# seed one user with a non-bps.go.id email, an employee, a team and an RK, then:
cd public && php -S 127.0.0.1:8092 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```
`KIP_SSO_LOGIN` defaults to off when `APP_ENV=local`, so a local password login works. Stop the server and delete the SQLite file when done.
