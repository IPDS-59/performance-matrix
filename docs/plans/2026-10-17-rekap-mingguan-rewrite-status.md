# Status: Rekap Mingguan rewrite (phase 1 and phase 2)

Branch `feature/rekap-mingguan-rewrite`. Final checks: `php artisan test` 476 passed, `vendor/bin/pint --test` ok, `vue-tsc --noEmit` clean, `vitest run` 85 passed, `vite build` ok. Before the work: 581 PHP and 91 vitest tests. The count fell because the claim tests went with the claim flow.

## Phase 1 (done)

- One member page `weekly.index` replaces the claim page and "Rencana Minggu Ini". Tables "Capaian minggu lalu" and "Rencana minggu ini", week picker, search, "Sinkronkan data saya", "Tambah". A row click opens a popup with every field, the result form and the next action.
- Add and edit are popups with the kipApp fields plus numeric target, satuan and capaian.
- Migration `plan_items`: `capaian`, `capaian_note`, `evidence_url`, `progress`, `time_start`, `time_end`. The push and complete actions send uraian as kipApp `capaian`, the link as `datadukung` and `progres`. With the new fields empty the payload is the same as before (tested).
- `weekly-plan.index` redirects to `weekly.index` and keeps `?week`. Menu has one "Rekap Mingguan". Guide updated.
- First sync after SSO connect: `SyncMyActivitiesJob` runs after the response, errors are only reported (tested, including a failing source).
- `DemoKegiatan.vue` and its route are deleted.

## Phase 2 (done unless listed under "Left")

- Weekly and monthly team recap per RK (`RkRecapPanel`): searchable member list with Sisipkan, Dibaca n/m, unread filter, "Menampilkan x dari N", person filter; empty paraphrase box, one target, satuan and capaian per RK, Simpan, Simpan dan kunci, Buka kunci. New table `rk_recaps`. The monthly panel shows the weekly paraphrases of the month.
- Quarterly (FRA), overview and Excel export read `plan_items` and `rk_recaps` through the rewritten `RecapAggregator`. The dashboard reads `plan_items`.
- Claim flow removed: controller, routes, models, tables (migration drops them), merge, prefill and correction code, components and tests. `kip_activities.is_claimed` is dropped; "Kegiatan Saya" now filters by sent to atasan.
- Popups: IKU (add, edit), teams (add, edit), RK (add, edit), employees (add). Searchable `EmployeePicker`.
- Handoff doc and guide updated.

## Where I kept the safer behavior

- Brief said the member recap may keep claim tables if the team recap needs them. Since the coordinator then allowed the full rewrite, I removed them.
- Saving a result on a row (`plan-items.result`) writes only to Kinetik. A row already marked done in kipApp is not re-sent by "Simpan capaian". To change a done row in kipApp, change it in kipApp and sync. I chose this so a plain save never writes to kipApp.
- `update` of a plan item is still refused once the plan left the `planned` state (same as before); results are edited through the result endpoint.
- The RK list in the add form is limited to the member's own synced RK when they have any, else all RK of their teams.

## Left, and why

- `Projects/Create.vue`, `Projects/Edit.vue` (610 lines) and `Employees/Edit.vue` stay pages: member picker, copy-from-last-year, work items, education and account linking make a safe conversion too large. `Teams/Members.vue` stays a page by design.
- The PJ page that set the weekly focus ("Fokus minggu ini") is gone with the old plan board. The endpoint `weekly-plan.focus` and the member-side display remain; there is no UI to write the focus yet. The same goes for the PJ "koreksi hasil" (`plan-items.evaluate`) and `PlanEvaluator`: routes and logic remain, the card that used them is gone.
- "Dibaca n/m" is kept in the browser for the meeting and is not stored.
- No visual check in a browser (optional in the brief). Pages are covered by type check, build and Inertia prop tests only, so a manual look at `RekapMingguan`, `TeamWeeklyRecap`, `MonthlyRecap` and `QuarterlyRecap` is still worth doing.

## Risks

- Old `recap_overrides` week and month rows, month summaries, week and month locks and all claims are deleted by migration `2026_10_18_000200`. The brief says nobody uses the app, so no data was kept. The migration has no `down`.
- `/rencana-minggu/item/...` routes keep their old paths on purpose, so stored notification links still work.
- A weekly item counts in every week it overlaps; monthly and quarterly sums count an item once by its start date. Long items can therefore show in two weeks but are never summed twice.
- The job runs `afterResponse`, not on a queue: production has no queue worker (cron only), so a queued job would never run.
- Incident: I ran `php artisan migrate:fresh --env=testing` once to check the new migrations. There is no `.env.testing`, so it ran against the local `.env` SQLite file (`database/database.sqlite`) and reset it. Only local dev data was affected (the file is ignored by git); it is migrated and empty now. Nothing in production or on another branch was touched.
