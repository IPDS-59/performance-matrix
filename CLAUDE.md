# Kinetik (performance_matrix): notes for agents

Laravel 12, Inertia, Vue 3, shadcn-vue, Tailwind. Production is PostgreSQL. Local and CI use SQLite. UI text is Indonesian. Code is English.

## Read first
- `docs/architecture/state-machines.md`: every lifecycle and where its rules live. Read it before you touch a status.
- `docs/kinetik/kipapp-integration-gaps.md`: what does and does not stay in step with kipApp.
- `docs/kinetik/rapat-spv-2026-11-09.md`: the requirement list from the spv, with status.

## Rules
- Kegiatan status: use `App\Kinetik\PlanStatus` and `$plan->stage()`. Do not compare new string literals.
- Save realisasi through `SaveRealisasiAction`. It keeps kipApp in step.
- A write to kipApp changes official records. Test with `Http::fake`. Ask the owner before a live write.
- Numbers in the UI go through `formatNumber`. Dropdowns use `SearchableSelect`.
- Each feature updates `resources/js/data/guide.ts` and both Slidev decks.

## Checks
`vendor/bin/pest tests/Feature`, `npx vitest run`, `npx vue-tsc --noEmit`, `vendor/bin/pint app tests`.

## Deploy
1. Merge the PR into `develop` (approval from the other account).
2. `git checkout develop && git pull`.
3. New migration: `./migrate-prod.sh migrate --force`. The release script does not migrate.
4. `./scripts/prod-release.sh`.
5. `./migrate-prod.sh tinker` runs local code on the production database. Read only.
