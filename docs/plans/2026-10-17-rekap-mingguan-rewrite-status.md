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

## kipApp-like kegiatan entry (section 7.3 of `2026-10-17-kipapp-entry-flow.md`)

Built per item number. Migration `2026_10_18_000300` adds `plan_items.is_capaian_skp` (default true), `plan_items.parent_id` and the table `member_skp_statuses`.

1. Two independent checkboxes "Gunakan periode tanggal" and "Gunakan jam" in `PlanItemFormDialog.vue` (state and payload in `usePlanEntry.ts`). `same:date_start` is gone, `after:time_start` stays, time inputs have `step=900`.
2. Behind `KIP_NULL_TIMES`. On: `PlanItem::kipFields()` sends `tanggalselesai` null for a one-day row and null times without a clock time. `ApiKipWriter::payload()` stops defaulting to 08:00 and 16:00. `CompletePlanItemAction` passes the kipApp row's own nulls back. Off: unchanged (tested both ways).
3. Quarter rule in `PlanItemController::checkQuarter`: both dates in `kinetik.kip.tahun` and in one quarter (the date inputs have the quarter as min and max). A pushed row must stay in its quarter when edited. Push and "Tandai selesai" of a row not yet in kipApp are refused before `date_start` ("Kegiatan belum bisa dikirim sebelum tanggal mulainya."), the button is disabled with the same hint. The scheduler is unchanged.
4. `description` required, min 10 (a text of only spaces arrives as null from TrimStrings, so the required message says so). Complete needs a capaian text of 10 characters ("Masukan Capaian", "Capaian minimal 10 karakter."). Saving a result stays free.
5. `defaultProgress`: 100 when the form date is before today (new rows only, until the user types a progres), else 0.
6. Checkbox "Masukan ke capaian SKP", stored in `is_capaian_skp`, sent as `iscapaianskp`. Default stays on (today's behaviour), not kipApp's off, so no RK ends with zero counted kegiatan.
7. Behind `KIP_EDIT_AFTER_PUSH`. `UpdatePlanItemAction` reads the kipApp row, sends `PUT /kegiatan` with the full row, and saves in Kinetik only after kipApp accepted. A sent row can only change `is_capaian_skp`.
8. Same flag. `DeletePlanItemAction` sends `DELETE /kegiatan`, then sets `status = cancelled` (row kept). Only while not sent. Confirm text "Apakah akan menghapus kegiatan ini?".
9. "Salin kegiatan" in the detail popup opens the form filled from the row. `parent_id` is stored (must be the member's own row) and sent as `parentid` (the source's kipApp id) at push.
10. "List Aktivitas" button next to Kegiatan opens `PresensiPickerDialog` (search, sorted by date, + button or double click). Endpoint `GET /rencana-minggu/presensi?date=` (`plan-items.presensi`) reads `GET /kegiatan/presensi` with the member token, `periodeid`, the quarter of the date and `nip_lama`. Rows without a date or text are dropped. Errors come back as `{message}` with 422. A pick sets description and one date and switches the range off.
11. Behind the existing `kinetik.kip.send_to_atasan`. Row checkboxes (rows with `can_send`), "Kirim ke atasan (n)" and "Batalkan kirim (n)", confirmation with kipApp's texts. `SendPlanItemAction` takes a list, checks every row first, then makes one call per member. Routes `plan-items.send-many` and `plan-items.unsend-many`. The single send and cancel also ask first. "Kirim SKP untuk dinilai" is not built.
12. `MemberSkpStatus` stores `statusskpid` and `statusskp` per member, year and quarter, each time `PushPlanItemAction` loads the quarter SKP. `PlanItemAccess`: send and cancel only at status 1, edit and delete only at 1 or 6, no row or no id means allowed. The page shows "SKP triwulan sudah dikirim untuk dinilai.".
13. Table gets "Bukti" (Lihat or "Tidak ada bukti dukung") and "Dikirim" (date). Search covers kegiatan, RK, capaian text, dates (ISO and as shown) and times.

Skipped: item 14 (no "rkid 0") by instruction. The RK and Projek selects in the form are now the searchable `SearchableSelect` (Popover and Command).

### Flags

| Env | Config | Default | Effect |
|---|---|---|---|
| `KIP_NULL_TIMES` | `kinetik.kip.null_times` | off | item 2 |
| `KIP_EDIT_AFTER_PUSH` | `kinetik.kip.edit_after_push` | off | items 7 and 8 (and the "capaian SKP" change of a sent row) |
| `KIP_SEND_TO_ATASAN` | `kinetik.kip.send_to_atasan` | off (existing) | item 11 |

### Not verified against live kipApp

Never called live. Everything is tested with `Http::fake`. Open questions 2, 3, 5, 7 and 8 of the entry-flow document are still open: null times on `PUT`, the wire format of `DELETE`, the exact fields of the presensi list, what `kirim` accepts, and `parentid`. Turn each flag on only after one test on a throwaway kegiatan.

### Risks

- The SKP status is read only when a plan is pushed, so it can be stale (the SKP may have been sent later). kipApp still refuses the call and its message is shown, and Kinetik data stays unchanged.
- With `KIP_EDIT_AFTER_PUSH` on, a text of "capaian" cleared in the form falls back to kipApp's current text on edit.
- Bulk send to several members' rows is one call per member, so a failure in the second member leaves the first one sent.
- The new rule "description at least 10 characters" and the quarter rule apply to every add and edit, including rows the PJ adds for a member.
- No browser check of the new form and popups (type check, build and tests only).
