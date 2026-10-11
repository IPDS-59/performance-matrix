<?php

namespace App\Actions\Kinetik;

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\RecapOverride;
use App\Models\WeeklyRecapRow;
use App\Models\WeeklyRecapRowClaim;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Save, merge and split the PJ's output rows of one week. The recap per RK
 * that the monthly and quarterly recaps and the Excel export read is derived
 * from these rows, so those pages keep working without knowing about them.
 */
class SaveWeeklyRecapRowsAction
{
    /**
     * Save the PJ text of rows. Each entry names the claims of its row.
     *
     * @param  list<array{claim_ids: list<int>, uraian?: ?string, obstacle?: ?string, solution?: ?string, follow_up_plan?: ?string}>  $rows
     */
    public function save(int $teamId, string $weekStart, array $rows, Employee $author): void
    {
        DB::transaction(function () use ($teamId, $weekStart, $rows, $author) {
            foreach ($rows as $input) {
                $row = $this->rowFor($teamId, $weekStart, $input['claim_ids'], $author);
                $row->update([
                    'pj_uraian' => $this->clean($input['uraian'] ?? null),
                    'obstacle' => $this->clean($input['obstacle'] ?? null),
                    'solution' => $this->clean($input['solution'] ?? null),
                    'follow_up_plan' => $this->clean($input['follow_up_plan'] ?? null),
                    'saved_at' => now(),
                ]);
            }
            $this->deriveRkText($teamId, $weekStart, $author);
        });
    }

    /**
     * Put claims in one row. Text already written for any of them is kept
     * (joined), so merging never loses what the PJ wrote.
     *
     * @param  list<int>  $claimIds  display order; the first row's text leads
     */
    public function merge(int $teamId, string $weekStart, array $claimIds, Employee $author): WeeklyRecapRow
    {
        return DB::transaction(function () use ($teamId, $weekStart, $claimIds, $author) {
            $old = WeeklyRecapRow::whereHas('claims', fn ($q) => $q->whereIn('activity_claims.id', $claimIds))->get();
            $first = ActivityClaim::findOrFail($claimIds[0]);

            $row = WeeklyRecapRow::create([
                'team_id' => $teamId,
                'project_id' => $first->project_id ?? $first->performancePlan?->project_id,
                'week_start' => $weekStart,
                'pj_uraian' => $this->join($old->pluck('pj_uraian')),
                'obstacle' => $this->join($old->pluck('obstacle')),
                'solution' => $this->join($old->pluck('solution')),
                'follow_up_plan' => $this->join($old->pluck('follow_up_plan')),
                'created_by' => $author->id,
            ]);

            WeeklyRecapRowClaim::whereIn('activity_claim_id', $claimIds)->delete();
            foreach ($claimIds as $id) {
                WeeklyRecapRowClaim::create(['weekly_recap_row_id' => $row->id, 'activity_claim_id' => $id]);
            }
            // Old rows that lost all their claims are gone.
            $old->each(fn (WeeklyRecapRow $r) => $r->claims()->exists() ? null : $r->delete());

            return $row;
        });
    }

    /** Every claim of the row shows as its own row again. */
    public function split(WeeklyRecapRow $row): void
    {
        $row->delete();
    }

    /**
     * The row of exactly these claims: the one they already share, or a new one.
     *
     * @param  list<int>  $claimIds
     */
    private function rowFor(int $teamId, string $weekStart, array $claimIds, Employee $author): WeeklyRecapRow
    {
        $links = WeeklyRecapRowClaim::whereIn('activity_claim_id', $claimIds)->get();
        $rowIds = $links->pluck('weekly_recap_row_id')->unique();

        if ($rowIds->count() > 1 || ($rowIds->count() === 1 && $links->count() !== count($claimIds))) {
            throw ValidationException::withMessages(['rows' => 'Baris berubah sejak halaman dibuka. Muat ulang halaman, lalu coba lagi.']);
        }
        if ($rowIds->count() === 1) {
            return WeeklyRecapRow::findOrFail($rowIds->first());
        }

        $first = ActivityClaim::with('performancePlan')->findOrFail($claimIds[0]);
        $row = WeeklyRecapRow::create([
            'team_id' => $teamId,
            'project_id' => $first->project_id ?? $first->performancePlan?->project_id,
            'week_start' => $weekStart,
            'created_by' => $author->id,
        ]);
        foreach ($claimIds as $id) {
            WeeklyRecapRowClaim::create(['weekly_recap_row_id' => $row->id, 'activity_claim_id' => $id]);
        }

        return $row;
    }

    /** Write the text of the rows to the per-RK override of the week. */
    private function deriveRkText(int $teamId, string $weekStart, Employee $author): void
    {
        $rows = WeeklyRecapRow::with('claims.performancePlan')
            ->where('team_id', $teamId)
            ->whereDate('week_start', $weekStart)
            ->get();

        /** @var array<string, array{plan: int, project: ?int, text: array<string, list<?string>>}> $byRk */
        $byRk = [];
        foreach ($rows as $row) {
            foreach ($row->claims as $claim) {
                $project = $claim->project_id ?? $claim->performancePlan?->project_id;
                $key = $claim->performance_plan_id.':'.($project ?? '');
                $byRk[$key] ??= ['plan' => $claim->performance_plan_id, 'project' => $project, 'text' => ['uraian' => [], 'obstacle' => [], 'solution' => [], 'follow_up_plan' => []]];
                $byRk[$key]['text']['uraian'][] = $row->pj_uraian;
                $byRk[$key]['text']['obstacle'][] = $row->obstacle;
                $byRk[$key]['text']['solution'][] = $row->solution;
                $byRk[$key]['text']['follow_up_plan'][] = $row->follow_up_plan;
            }
        }

        $year = Carbon::parse($weekStart)->year;
        foreach ($byRk as $rk) {
            RecapOverride::updateOrCreate(
                [
                    'team_id' => $teamId, 'performance_plan_id' => $rk['plan'], 'project_id' => $rk['project'],
                    'period_type' => 'week', 'period_year' => $year, 'period_month' => null, 'period_quarter' => null, 'week_start' => $weekStart,
                ],
                [
                    'uraian' => $this->join(collect($rk['text']['uraian'])),
                    'obstacle' => $this->join(collect($rk['text']['obstacle'])),
                    'solution' => $this->join(collect($rk['text']['solution'])),
                    'follow_up_plan' => $this->join(collect($rk['text']['follow_up_plan'])),
                    'created_by' => $author->id,
                ],
            );
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param  Collection<int, ?string>  $values */
    private function join(Collection $values): ?string
    {
        $joined = $values->filter(fn ($v) => filled($v))->map(fn ($v) => trim((string) $v))->unique()->implode("\n");

        return $joined === '' ? null : $joined;
    }
}
