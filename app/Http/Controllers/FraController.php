<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\FraMonthValue;
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
        $month = min(12, max(1, (int) $request->query('month', now()->month)));
        $quarter = (int) ceil($month / 3);
        $sakip = $request->filled('sakip') ? (float) $request->query('sakip') : null;
        $user = $request->user();
        $ledTeamIds = $this->ledTeamIds($user);

        $built = $report->build(FraIndicator::with(['quarterValues', 'monthValues'])->where('year', $year)->get(), $quarter, $sakip, $month);
        $teams = Team::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Kinetik/Fra', [
            ...$built,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
            'teams' => $teams,
            'isAdmin' => $user->hasRole('admin'),
            'editableIds' => collect($built['indicators'])
                ->filter(fn (array $row) => $user->hasRole('admin') || ($row['owner_team_id'] !== null && in_array($row['owner_team_id'], $ledTeamIds, true)))
                ->pluck('id')->values()->all(),
        ]);
    }

    /** Kendala, solusi, tindak lanjut and the links of one indicator for one month. */
    public function updateAnalysis(Request $request, FraIndicator $indicator, int $month): RedirectResponse
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        abort_unless($this->canEnter($request->user(), $indicator), 403, 'Hanya PJ tim pemilik indikator yang dapat mengisi.');

        $data = $request->validate([
            'obstacle' => ['nullable', 'string', 'max:3000'],
            'solution' => ['nullable', 'string', 'max:3000'],
            'follow_up' => ['nullable', 'string', 'max:3000'],
            'pic' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'string', 'max:100'],
            'evidence_url' => ['nullable', 'string', 'max:2000'],
            'previous_follow_up_url' => ['nullable', 'string', 'max:2000'],
        ]);

        FraMonthValue::updateOrCreate(
            ['fra_indicator_id' => $indicator->id, 'month' => $month],
            [...$data, 'updated_by' => $request->user()->employee?->id],
        );

        return back()->with('success', "Analisis {$indicator->code} bulan {$month} disimpan.");
    }

    /** All twelve months of one indicator in one save, like filling the Data Monitoring sheet. */
    public function updateMonths(Request $request, FraIndicator $indicator): RedirectResponse
    {
        abort_unless($this->canEnter($request->user(), $indicator), 403, 'Hanya PJ tim pemilik indikator yang dapat mengisi.');

        $data = $request->validate([
            'months' => ['required', 'array'],
            'months.*.x' => ['nullable', 'numeric'],
            'months.*.y' => ['nullable', 'numeric'],
        ]);

        foreach ($data['months'] as $month => $cell) {
            abort_unless((int) $month >= 1 && (int) $month <= 12, 422);
            $x = $cell['x'] ?? null;
            $y = $cell['y'] ?? null;
            FraMonthValue::updateOrCreate(
                ['fra_indicator_id' => $indicator->id, 'month' => (int) $month],
                ['x' => $x, 'y' => $y, 'updated_by' => $request->user()->employee?->id],
            );
        }

        return back()->with('success', "Isian bulanan {$indicator->code} disimpan.");
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
