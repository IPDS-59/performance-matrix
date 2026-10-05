<?php

namespace App\Http\Controllers;

use App\Models\KipActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KipActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'all'); // all | claimed | unclaimed

        // Period filter: a week, month or quarter around an anchor date. An
        // activity counts when it runs during the period.
        $period = in_array($request->query('period'), ['week', 'month', 'quarter'], true) ? $request->query('period') : 'all';
        $anchor = rescue(fn () => Carbon::parse((string) $request->query('date', now()->toDateString())), now(), false);
        [$from, $to] = match ($period) {
            'week' => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'quarter' => [$anchor->copy()->firstOfQuarter(), $anchor->copy()->lastOfQuarter()],
            default => [null, null],
        };
        $inPeriod = fn (Builder $q) => $q->when($from, fn (Builder $q) => $q->duringWeek($from->toDateString(), $to->toDateString()));

        // Admins (kipApp managers) see every employee's activities; everyone else
        // is scoped to their own linked employee.
        $canViewAll = $request->user()->can('manage-kip-integration');
        $ownEmployeeId = $request->user()->employee?->id;

        $activities = KipActivity::query()
            ->with('employee:id,name,display_name,nip_lama')
            ->when(! $canViewAll, fn (Builder $q) => $q->where('employee_id', $ownEmployeeId))
            ->tap($inPeriod)
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('rk_name', 'like', "%{$search}%")
                        ->orWhere('nip_lama', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn (Builder $e) => $e->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status === 'claimed', fn (Builder $q) => $q->where('is_claimed', true))
            ->when($status === 'unclaimed', fn (Builder $q) => $q->where('is_claimed', false))
            ->orderByDesc('activity_date_start')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (KipActivity $a) => [
                'id' => $a->id,
                'employee_name' => $a->employee?->display_name ?? $a->employee?->name ?? '—',
                'nip_lama' => $a->nip_lama,
                'description' => $a->description,
                'rk_name' => $a->rk_name,
                'date_start' => $a->activity_date_start?->toDateString(),
                'date_end' => $a->activity_date_end?->toDateString(),
                'progress' => $a->progress,
                'evidence_url' => $a->evidence_url,
                'is_claimed' => $a->is_claimed,
            ]);

        $scopeQuery = fn () => KipActivity::query()
            ->when(! $canViewAll, fn (Builder $q) => $q->where('employee_id', $ownEmployeeId))
            ->tap($inPeriod);

        return Inertia::render('Kinetik/Activities', [
            'activities' => $activities,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'period' => $period,
                'date' => $anchor->toDateString(),
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'stats' => [
                'total' => $scopeQuery()->count(),
                'claimed' => $scopeQuery()->where('is_claimed', true)->count(),
            ],
            'canViewAll' => $canViewAll,
        ]);
    }
}
