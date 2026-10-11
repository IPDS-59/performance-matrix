<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\FraQuarterValue;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\FraReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kertas Kerja FRA" (sheet LK_Prov). The PJ of the team that owns an
 * indicator enters its quarterly allocation and realisation, the head reads,
 * and admins assign the owner team and can enter everything (Q12).
 */
class FraController extends Controller
{
    public function index(Request $request, FraReport $report): Response
    {
        abort_unless(PlanComplianceController::canView($request->user()), 403);

        $year = (int) $request->query('year', now()->year);
        $quarter = min(4, max(1, (int) $request->query('quarter', now()->quarter)));
        $sakip = $request->filled('sakip') ? (float) $request->query('sakip') : null;
        $user = $request->user();
        $ledTeamIds = $this->ledTeamIds($user);

        $built = $report->build(FraIndicator::with('quarterValues')->where('year', $year)->get(), $quarter, $sakip);
        $teams = Team::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Kinetik/Fra', [
            ...$built,
            'year' => $year,
            'quarter' => $quarter,
            'teams' => $teams,
            'isAdmin' => $user->hasRole('admin'),
            'editableIds' => collect($built['indicators'])
                ->filter(fn (array $row) => $user->hasRole('admin') || ($row['owner_team_id'] !== null && in_array($row['owner_team_id'], $ledTeamIds, true)))
                ->pluck('id')->values()->all(),
        ]);
    }

    public function updateValues(Request $request, FraIndicator $indicator, int $quarter): RedirectResponse
    {
        abort_unless($quarter >= 1 && $quarter <= 4, 404);
        abort_unless($this->canEnter($request->user(), $indicator), 403, 'Hanya PJ tim pemilik indikator yang dapat mengisi.');

        $data = $request->validate([
            'allocation_x' => ['nullable', 'numeric'],
            'allocation_y' => ['nullable', 'numeric'],
            'realization_x' => ['nullable', 'numeric'],
            'realization_y' => ['nullable', 'numeric'],
            'obstacle' => ['nullable', 'string', 'max:3000'],
            'solution' => ['nullable', 'string', 'max:3000'],
            'follow_up' => ['nullable', 'string', 'max:3000'],
            'pic' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'string', 'max:100'],
            'evidence_url' => ['nullable', 'string', 'max:2000'],
            'previous_follow_up_url' => ['nullable', 'string', 'max:2000'],
        ]);

        FraQuarterValue::updateOrCreate(
            ['fra_indicator_id' => $indicator->id, 'quarter' => $quarter],
            [...$data, 'updated_by' => $request->user()->employee?->id],
        );

        return back()->with('success', "Isian {$indicator->code} TW {$quarter} disimpan.");
    }

    public function updateOwner(Request $request, FraIndicator $indicator): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['owner_team_id' => ['nullable', 'integer', 'exists:teams,id']]);

        $indicator->update(['owner_team_id' => $data['owner_team_id'] ?? null]);

        return back()->with('success', 'Tim pemilik indikator disimpan.');
    }

    private function canEnter(User $user, FraIndicator $indicator): bool
    {
        return $user->hasRole('admin')
            || ($indicator->owner_team_id !== null && in_array($indicator->owner_team_id, $this->ledTeamIds($user), true));
    }

    /** @return list<int> */
    private function ledTeamIds(User $user): array
    {
        $employee = $user->employee;

        return $employee instanceof Employee ? Team::where('leader_id', $employee->id)->pluck('id')->all() : [];
    }
}
