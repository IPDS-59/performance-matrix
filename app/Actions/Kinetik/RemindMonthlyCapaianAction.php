<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\Team;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Early in a month: members with kegiatan of the month before that still lack a target or a capaian get a bell notification that opens
 * "Isi Capaian Bulanan" on that month, and each PJ gets one summary of who. Without them a team never shows in the IKU figures or the
 * recap. Each notification goes out once per member and month, so a repeated run adds nothing.
 */
class RemindMonthlyCapaianAction
{
    /** @return array{members: int, pj: int} notifications sent */
    public function execute(?Carbon $now = null, ?string $month = null): array
    {
        $first = $month !== null
            ? Carbon::parse($month.'-01')->startOfMonth()
            : ($now ?? now(config('app.schedule_timezone', 'Asia/Makassar')))->copy()->subMonthNoOverflow()->startOfMonth();
        $key = $first->format('Y-m');
        $sent = ['members' => 0, 'pj' => 0];

        $missing = PlanItem::where('status', '!=', 'cancelled')
            ->whereBetween('date_start', [$first->toDateString(), $first->copy()->endOfMonth()->toDateString()])
            ->where(fn ($q) => $q->whereNull('target')->orWhere('target', '<=', 0)->orWhereNull('capaian'))
            ->get(['employee_id', 'team_id'])
            ->groupBy('employee_id')->map->count();
        if ($missing->isEmpty()) {
            return $sent;
        }

        $members = Employee::with('user')->whereIn('id', $missing->keys())->where('is_active', true)->whereNotNull('user_id')->get()->keyBy('id');
        $label = $first->locale('id')->translatedFormat('F Y');

        foreach ($members as $member) {
            if (Cache::add("capaian-reminder:member:{$member->id}:{$key}", true, now()->addDays(35))) {
                $member->user->notify(new KinetikNotification(
                    'capaian_reminder',
                    "{$missing[$member->id]} kegiatan Anda di {$label} belum punya target atau capaian. Isi sekarang di Isi Capaian Bulanan.",
                    route('realisasi.monthly', ['month' => $key]),
                ));
                $sent['members']++;
            }
        }

        Team::with(['members', 'leader.user'])->get()->each(function (Team $team) use ($members, $missing, $key, $label, &$sent) {
            $leader = $team->leader;
            $others = $team->members->filter(fn (Employee $m) => $members->has($m->id) && $m->id !== $leader?->id);
            if ($leader?->user === null || $others->isEmpty() || ! Cache::add("capaian-reminder:pj:{$team->id}:{$key}", true, now()->addDays(35))) {
                return;
            }
            $names = $others->take(8)->map(fn (Employee $m) => ($m->display_name ?? $m->name).' ('.$missing[$m->id].')')->implode(', ');
            $more = $others->count() > 8 ? ' dan '.($others->count() - 8).' lainnya' : '';
            $leader->user->notify(new KinetikNotification(
                'capaian_reminder_pj',
                "{$others->count()} anggota {$team->name} belum mengisi target atau capaian {$label}: {$names}{$more}.",
                route('iku-progress.index'),
            ));
            $sent['pj']++;
        });

        return $sent;
    }
}
