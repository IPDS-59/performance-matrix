<?php

namespace App\Http\Controllers;

use App\Models\KipActivity;
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

        $activities = KipActivity::query()
            ->with('employee:id,name,display_name,nip_lama')
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

        return Inertia::render('Kinetik/Activities', [
            'activities' => $activities,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => KipActivity::count(),
                'claimed' => KipActivity::where('is_claimed', true)->count(),
            ],
        ]);
    }
}
