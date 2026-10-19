# Handoff: match Kinetik planning to the kipApp flow (2026-10-12)

Written for the next agent. You can run on another laptop. Everything you need is in this repo or in this file. Local notes of the first agent (memory, knowledge base, `docs/kinetik/`) are not on your machine.

## Source

A recording of a walkthrough of kipApp (screen share, 11 minutes, 2026-10-11). The speaker is the product owner. The user is the product owner's spouse, a kipApp user of BPS Sulteng. The transcript was made with Whisper, so names are imperfect ("PKA" is PK, "iqy" is IKI). The sequence below was checked against the frames.

## The kipApp flow, as described

1. **Start of year.** PK (Perjanjian Kinerja), Tujuan, Sasaran and IKU are entered. The speaker does not know whether an admin or the PJ does it. Then Tim Kerja, RK Ketua, Proyek and RK Anggota. Leaders sometimes make member RKs, but members usually make their own.
2. **SKP Tahunan** (Perencanaan Kinerja > Tahunan > Add). The year is chosen and the period is 1 Jan to 31 Dec. Someone who transferred in mid-year gets two SKPs for the year (for example 1 Jan to 30 Sep and 1 Oct to 31 Dec). The member picks RKs from the RK Anggota list, so RK Anggota must exist first. The member sends it and the head approves it.
3. **SKP Periodik.** It was monthly and the central office changed it to every 3 months. It can be made only after the head approved the annual SKP. Add, choose the quarter, choose the RKs.
4. **Rencana Kinerja.** Per period, with Utama and Tambahan rows and a "Copy Rencana Kinerja" button. Evidence (bukti dukung) is added here. Extra RKs (rencana kinerja tambahan) can be added outside the SKP.
5. **IKI dan Target.** One indicator and target per RK. Her indicators were already filled at the start of the year, so she was unsure when someone creates them.
6. **Pelaksanaan.** Daily kegiatan with a date period or a clock time, a description, an achievement text and evidence.

## What was built on 2026-10-12

- `ApiKipWriter::sendActivities()` and `cancelSend()`: `POST /kegiatan/kirim` and `/kegiatan/batal` with `{id: [...]}`. **Not verified against live kipApp.** The payload is decoded from kipApp's own code (see `docs/kinetik/spec-planning.md` section 5 on the first agent's machine). The buttons stay hidden until the setting `KIP_SEND_TO_ATASAN=true` is in `.env` (config `kinetik.kip.send_to_atasan`, default false, and the routes answer 403 while it is off). Test on a throwaway kegiatan of the owner's own SKP first. Then turn it on.
- `SendPlanItemAction` (send and cancel), routes `plan-items.send` and `plan-items.unsend`, column `plan_items.kip_sent_at` (migration `2026_10_12_000100`). `ReconcilePlanItemsAction` copies `sent_at` from the synced kipApp kegiatan, so kipApp wins.
- `MemberPlanFlow` (service): five steps per member from local data only (RK Anggota, SKP triwulan, rencana minggu ini, ada di kipApp, terkirim ke atasan). The SKP step is `unknown` until a push shows whether the quarter SKP exists. It never guesses the SKP Tahunan or its approval, because Kinetik does not read them.
- UI on `Rencana Minggu Ini`:
  - A flow strip per member. It spells out the first blocker.
  - A collapsible guide of the kipApp order (`resources/js/data/planFlowGuide.ts`).
  - Plan cards (`PlanItemCard.vue`) with a five-segment stage bar and one next action per stage.
  - Dialogs for cancel, send and unsend, in place of `confirm()`.
  - Accordions for "RK belum ada kegiatan" and "Kegiatan belum selesai".
  - Base text at 14px, and nothing under 12px.
  - Stage logic in `composables/usePlanStage.ts`, with tests.
- Buku Pedoman (`resources/js/data/guide.ts`) updated for the flow strip, "Kirim ke atasan" and the OTP page.
- Tests: 14 new PHP tests and 3 new vitest tests. The UI was **not looked at in a browser**, because the rule is never to run the dev server for Kinetik. Please check it on the user's machine.

## Not built, and why

| Item | Why it waits |
|---|---|
| "Buat SKP TW" (Q10, `POST /skp/bulanan`) | The verified call needs the yearly SKP id and the rows of `GET /skp/rk/copy/bulanan`. Which `jenis` value lists the yearly SKP is not known. The flow above also says the annual SKP must be approved first, so the button must check that. Confirm both with the owner. |
| SKP submission (`POST /skp/penetapan/kirim`) | Submits an official record. Decoded only. Needs the owner's consent and a test on her own SKP. |
| Two SKPs in one year (transfers) | Matching today assumes one quarterly SKP per quarter. The owner is such a case, so test with her data. |
| RK to IKU mapping, weekly plans per IKU | Still undecided. The recording does not settle it. The speaker did not say when IKI is created. |
| Who creates RK Anggota at the start of year | The speaker is unsure whether an admin or the PJ does it. Ask. |

## Questions for the kipApp user (ask when she is back)

1. Who enters PK, Tujuan, Sasaran and IKU, and who creates RK Ketua and RK Anggota at the start of the year?
2. Is IKI created by the leader or the member, and when?
3. After the annual SKP is approved, can a member create the quarterly SKP alone, or does the leader also have a step?
4. Can a member send a kegiatan to the atasan when progres is under 100? Or must the capaian be filled first?
5. Can a sent kegiatan always be cancelled, or only before the atasan rates it?
6. For a transferred member with two annual SKPs, which one does the quarterly SKP belong to?

## Login state (separate from planning, but you will hit it)

- Staff log in to Kinetik with their BPS SSO password. These parts are built: SSO login, first-login default password, connect page, and the OTP step (`KipOtpController`, `KipOtpChallenge`, `KipLoginClient::loginWithOtp`).
- The OTP step was verified only up to the form. An OTP account returns a form with the fields `otp`, `login`, `cancel` and `back`. **Nobody submitted a real code.**
- **BPS is moving to a new SSO** (`accounts.bps.go.id`, login area `pegawai`). It has a Cloudflare captcha and passkeys, so a server cannot log in with plain HTTP. All BPS apps move after December 2026. New staff such as Jihan are already there. Registering an SSO client needs a `.go.id` domain, and a `.go.id` subdomain needs Kantor Pusat approval, which is slow.
- The first-login default password works until `KIP_DEFAULT_PASSWORD_UNTIL` in `.env.production` (not in git). The user moved it to 2026-11-30 on 2026-10-12. It takes effect after a new zip and the setup link, because the setup step caches the configuration.
- Not built yet, and the first priority for December: a local-login fallback (a per-account marker for new-SSO members and a global switch such as `KIP_SSO_LOGIN=false`). When the old SSO closes, every SSO login fails without it.
- kipApp has a "Public API" page with `POST /token/list` and `POST /token/generate`. It is probably the official way to get a server token. Ask the kipApp team.
- The kipApp token sits in browser `localStorage` under `ka-p-fa0de6036011042cc37f296a888475a2`. Each character code is shifted by 2188321. This comes from the deobfuscated `main.js`, and nobody tested it with a real login. A bookmarklet can send the token to Kinetik. It is hard for non-technical users, so treat it as optional.

## Pitfalls on this repo

- `deploy.sh` ran `composer install --no-dev` in the checkout. It now restores the dev packages at the end. If `php artisan test` says "Command test is not defined", run `composer install`.
- `node_modules` was damaged more than once (missing build folders of `@lucide/vue`, `@tanstack/table-core`, `d3-interpolate-path`). `pnpm install --force --frozen-lockfile` fixed it.
- Never run the dev server. Never commit `docs/credentials` or `docs/kinetik/`. No attribution lines in commits. Ask before writing to the live kipApp and use the owner's own SKP for any test. Do not paste tokens.
- Tests: PHP `php artisan test`, JS `pnpm exec vitest run`, types `pnpm exec vue-tsc --noEmit`. State at handoff: 579 PHP tests, 91 vitest tests, `vue-tsc` clean, `vite build` passes.

## Repo and release state (2026-10-12)

- Branches: work happens on `develop`. `main` is the release branch. Merge `develop` into `main` with a pull request and a **merge commit**, never a squash. Squashing makes the two branches diverge.
- Rules on `main` and `develop`: an approving review from an account other than the PR author, and the required checks (Test, Lint PHP, Lint Frontend, Build, TypeScript Check). The approval must be on the latest commit.
- Branch work from the latest `develop`. Housekeeping deletes a branch after its PR is merged. Never push more commits to a branch whose PR is merged.
- Dependabot now targets `develop`. On 2026-10-12 all 19 old Dependabot PRs were replaced by one update (PR #130). `spatie/laravel-permission` is at ^8.
- The Release workflow failed to start on every push to `main` since June, because it passed an input (`package-manager`) that the shared workflow does not define. The line is removed in the PR that added the send setting. If the workflow still fails, read the run log.
- Pint (`vendor/bin/pint --test`) runs in CI over the whole repo. Run it before you push.
- Deploy: `./deploy.sh` builds the zip from the working tree and injects `.env.production` as `.env`. Upload it, open the setup link, then run `migrate-prod.sh`. This release adds the migration for `plan_items.kip_sent_at`. Run the migration right after the upload.
- State at this handoff: 580 PHP tests, 92 vitest tests, `vue-tsc` clean.

## Planning redesign: decisions of 2026-10-16 (from the team lead and the product owner)

A clickable prototype is in `docs/prototype/kinetik-rencana-rekap.html` (one HTML file, sample data). Build the real version from it, one PR per part.

1. **No claim flow.** The Rekap Mingguan claim form goes away. kipApp data is already synced, so members add and edit kegiatan directly. Planning (Rencana Minggu Ini) and the weekly recap become one page per member: input and monitoring in the same table.
2. **Weekly cycle.** Meeting in week 1: discuss the plan for the next week and enter targets. Meeting in the following weeks: discuss last week's capaian, then plan for the current week and enter targets. The page has two tables: "Capaian minggu lalu" and "Rencana minggu ini".
3. **Tables, not cards.** Sent items show as a table with a few columns. Clicking a row opens a popup with every field.
4. **Popup form.** A button opens the form as a popup, like kipApp. After saving, the row appears in the table.
5. **Target and capaian.** Each member enters and edits their own target and capaian. The PJ does not edit per-person values. The PJ adds one target and one capaian for the whole RK, next to the paraphrase. Target and capaian are **numbers**, never free text, and both use one **satuan** (unit) field (member entries and the RK's). Show capaian against target, for example 9 of 11 blok (82%). The description of what was done goes in the kegiatan text and the evidence link.
6. **Paraphrase (PJ).** No merged rows, and no prefill from every member (the box fills up when many people share an RK). Show a context panel beside an empty box: one line per member, expandable, with a "Sisipkan" button per sentence so the PJ picks what to reuse. Show "Dibaca n/m" as a nudge, not a lock. The member list must cope with 20 or more people: a search box (name or text), a scroll area with a fixed height, an "unread only" filter, and a "Menampilkan x dari N" count. Lock stays as it is. The weekly paraphrase can feed the monthly recap.
7. **Recap linked to members.** Weekly and monthly team recaps read from each member's entries and can be filtered per person.
8. **Own rows only.** The member page shows only the signed-in person. Done in the Rekap Mingguan rewrite (PR #146), which replaced PR #144. The PJ's team view is the recap and compliance pages.
9. **Simpler menu.** Reduce the sidebar. Exact items are not decided: ask the team lead.
10. **First sync after connecting SSO.** The "RK belum ada kegiatan" message still says an admin must run the sync. Replace it with "Tekan Sinkronkan data saya", and run the first sync automatically when a member finishes connecting SSO.

Open questions: where does item 5's "choose" (kegiatan from presensi, as in kipApp) get its list from? Ask for a screenshot of that kipApp screen. Should changes to capaian be logged with who and when?

## Rekap Mingguan rewrite (2026-10-17)

Branch `feature/rekap-mingguan-rewrite`. Nobody uses the app yet, so the claim flow was removed instead of kept beside the new one.

- **Member page.** `/rekap-mingguan` (`weekly.index`, `MemberWeekController`, `BuildMemberWeekAction`, `Pages/Kinetik/RekapMingguan.vue`) is the only member page. It shows the signed-in member's `plan_items` for last week and this week. A PJ sees only their own rows. `/rencana-minggu` redirects to it. The PJ's weekly focus is shown to the member there; the PJ page that set it is gone (see status file).
- **plan_items** gained `capaian` (decimal), `capaian_note` (uraian, sent to kipApp as `capaian`), `evidence_url` (`datadukung`), `progress` (`progres`), `time_start`, `time_end`. Target and capaian are numbers. `PlanItemController@result` saves a result in Kinetik only; "Tandai selesai" is what writes to kipApp.
- **First sync.** `ConnectKipAccountAction` dispatches `SyncMyActivitiesJob` after the response (best effort, errors only reported). The sync logic is in `SyncMemberActivitiesAction`, shared with the "Sinkronkan data saya" button.
- **Team recap.** New table `rk_recaps` (team, RK, `period_type` week or month, `period_start`, paraphrase, target, satuan, capaian, `locked_at`, `locked_by`). `RecapAggregator` now reads `plan_items` and `rk_recaps`. Weekly and monthly pages show one RK panel at a time (`RkRecapPanel.vue`). The quarterly (FRA) page keeps `recap_overrides` and `recap_summaries` for its text, and takes its numbers from the monthly `rk_recaps`, else from the members' plan items. Locks (`recap_locks`) now apply to quarters only; weeks and months lock per RK.
- **Removed.** `activity_claims`, `weekly_recap_rows`, `weekly_recap_row_claims`, `weekly_team_notes` (migration `2026_10_18_000200_drop_claim_tables`), `kip_activities.is_claimed`, the merge, prefill and per-claim correction endpoints and their components. The dashboard reads `plan_items` (capaian x 100 / target) instead of claims.
- **Popups.** Add and edit of IKU, teams and RK, and add of employees, run in popups from the index pages (`FormDialog`).
