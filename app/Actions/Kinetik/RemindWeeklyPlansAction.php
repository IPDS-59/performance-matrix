<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\Team;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Monday 09:00: members with no plan for the week get a bell notification and
 * each PJ gets one summary of who is missing. The head is not notified (Q8).
 * Each notification goes out once per week, so a repeated run adds nothing.
 */
class RemindWeeklyPlansAction
{
    /** @return array{members: int, pj: int} notifications sent */
    public function execute(?Carbon $now = null): array
    {
        $monday = ($now ?? now(config('app.schedule_timezone', 'Asia/Makassar')))->copy()->startOfWeek(Carbon::MONDAY);
        $week = $monday->toDateString();
        $sent = ['members' => 0, 'pj' => 0];

        $planned = PlanItem::duringWeek($week, $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->pluck('employee_id')->unique()->flip();

        Team::with(['members' => fn ($q) => $q->where('employees.is_active', true)->whereNotNull('employees.user_id'), 'members.user', 'leader.user'])
            ->get()
            ->each(function (Team $team) use ($week, $planned, &$sent) {
                $missing = $team->members->reject(fn (Employee $m) => $planned->has($m->id));

                foreach ($missing as $member) {
                    if (Cache::add("plan-reminder:member:{$member->id}:{$week}", true, now()->addDays(8))) {
                        $member->user->notify(new KinetikNotification(
                            'plan_reminder',
                            'Anda belum membuat rencana kerja untuk minggu ini. Isi sekarang di Rencana Minggu Ini.',
                            route('weekly-plan.index', ['team' => $team->id, 'week' => $week]),
                        ));
                        $sent['members']++;
                    }
                }

                $leader = $team->leader;
                $others = $missing->reject(fn (Employee $m) => $m->id === $leader?->id);
                if ($leader?->user && $others->isNotEmpty() && Cache::add("plan-reminder:pj:{$team->id}:{$week}", true, now()->addDays(8))) {
                    $names = $others->map(fn (Employee $m) => $m->display_name ?? $m->name)->take(8)->implode(', ');
                    $more = $others->count() > 8 ? ' dan '.($others->count() - 8).' lainnya' : '';
                    $leader->user->notify(new KinetikNotification(
                        'plan_reminder_pj',
                        "{$others->count()} anggota {$team->name} belum membuat rencana minggu ini: {$names}{$more}.",
                        route('weekly-plan.index', ['team' => $team->id, 'week' => $week]),
                    ));
                    $sent['pj']++;
                }
            });

        return $sent;
    }
}
