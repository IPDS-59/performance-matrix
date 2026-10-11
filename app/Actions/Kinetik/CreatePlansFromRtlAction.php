<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\RecapLock;
use App\Models\RecapOverride;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Each RTL of a locked quarterly recap becomes a plan item in the next
 * quarter (Q13 in the planning spec). The PIC owns it (the PJ when the RTL has
 * no PIC) and its due date is the RTL's Batas Waktu. Items stay editable.
 * Safe to run every day: an RTL is turned into a plan once, even if that plan
 * is cancelled later.
 */
class CreatePlansFromRtlAction
{
    /** @return int number of plan items created */
    public function execute(?Carbon $now = null): int
    {
        $now = ($now ?? now(config('app.schedule_timezone', 'Asia/Makassar')))->copy();
        $quarterStart = $now->copy()->firstOfQuarter();
        $previous = $quarterStart->copy()->subDay();
        $start = $now->copy()->startOfDay()->max($quarterStart);

        $created = [];

        RecapLock::with('team.leader')
            ->where('period_type', 'quarter')
            ->where('period_year', $previous->year)
            ->where('period_quarter', $previous->quarter)
            ->get()
            ->each(function (RecapLock $lock) use ($previous, $quarterStart, $start, &$created) {
                RecapOverride::where('team_id', $lock->team_id)
                    ->where('period_type', 'quarter')
                    ->where('period_year', $previous->year)
                    ->where('period_quarter', $previous->quarter)
                    ->whereNotNull('follow_up_plan')
                    ->get()
                    ->filter(fn (RecapOverride $o) => trim((string) $o->follow_up_plan) !== '')
                    ->each(function (RecapOverride $override) use ($lock, $quarterStart, $start, &$created) {
                        if (PlanItem::where('source', 'rtl')->where('source_ref', $override->id)->exists()) {
                            return;
                        }

                        $owner = $override->follow_up_pic_employee_id ?? $lock->team->leader_id;
                        if ($owner === null) {
                            return;
                        }

                        $end = Carbon::parse($override->follow_up_deadline ?? $quarterStart->copy()->lastOfQuarter())->startOfDay()->max($start);

                        PlanItem::create([
                            'team_id' => $lock->team_id,
                            'employee_id' => $owner,
                            'performance_plan_id' => $override->performance_plan_id,
                            'project_id' => $override->project_id,
                            'description' => Str::limit(trim((string) $override->follow_up_plan), 1000, ''),
                            'date_start' => $start->toDateString(),
                            'date_end' => $end->toDateString(),
                            'source' => 'rtl',
                            'source_ref' => $override->id,
                        ]);

                        $created[$owner][] = $lock->team_id;
                    });
            });

        foreach ($created as $employeeId => $teamIds) {
            $user = Employee::find($employeeId)?->user;
            $user?->notify(new KinetikNotification(
                'plan_rtl',
                count($teamIds).' RTL triwulan lalu menjadi rencana kerja Anda. Periksa dan ubah bila perlu.',
                route('weekly-plan.index', ['team' => $teamIds[0], 'week' => $start->toDateString()]),
            ));
        }

        return array_sum(array_map('count', $created));
    }
}
