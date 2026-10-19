<?php

namespace App\Http\Controllers;

use App\Kinetik\Credit\CreditCalculator;
use App\Models\CreditQuarterOverride;
use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Angka Kredit: each person sees their own; a PJ sees the members of the
 * teams they lead; the head and the admin see the whole office.
 */
class CreditController extends Controller
{
    /** Sort order of the team list: who can be proposed first. */
    private const STATUS_ORDER = ['ready' => 0, 'ak_ready' => 1, 'near' => 2, 'progress' => 3, 'top' => 4, 'no_data' => 5, 'non_jf' => 6];

    public function __construct(private readonly CreditCalculator $calculator) {}

    public function mine(Request $request): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        return Inertia::render('Credit/Mine', [
            'credit' => $this->calculator->forEmployee($employee->load(['career', 'performanceRatings', 'creditOverrides.editor'])),
        ]);
    }

    public function team(Request $request): Response
    {
        $teams = self::visibleTeams($request);
        abort_if($teams->isEmpty(), 403, 'Hanya PJ, pimpinan dan admin yang dapat melihat Angka Kredit tim.');

        $selected = $teams->firstWhere('id', $request->integer('team')) ?? null;
        $scope = $selected ? collect([$selected]) : $teams;

        $employees = Employee::query()
            ->with(['career', 'performanceRatings', 'creditOverrides.editor'])
            ->where('is_active', true)
            ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $scope->pluck('id')))
            ->orderBy('name')
            ->get();

        $rows = $employees
            ->map(fn (Employee $e) => $this->calculator->forEmployee($e))
            ->sortBy([
                fn (array $a, array $b) => self::STATUS_ORDER[$a['status']] <=> self::STATUS_ORDER[$b['status']],
                fn (array $a, array $b) => ($a['gap'] ?? INF) <=> ($b['gap'] ?? INF),
            ])
            ->values();

        return Inertia::render('Credit/Team', [
            'teams' => $teams->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'selectedTeamId' => $selected?->id,
            'rows' => $rows,
            'canEditBase' => $request->user()->hasRole('admin'),
        ]);
    }

    /**
     * The AK from the last PAK (or the 2022 integration PAK). The employee can
     * enter their own; the admin checks it against the document. Clearing
     * both fields returns to the estimate from kipApp ratings.
     */
    public function updateBase(Request $request, Employee $employee): RedirectResponse
    {
        $isAdmin = $request->user()->hasRole('admin');
        abort_unless($isAdmin || $employee->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'ak_base' => ['nullable', 'numeric', 'min:0', 'max:9999', 'required_with:ak_base_date'],
            'ak_base_date' => ['nullable', 'date', 'required_with:ak_base', 'before_or_equal:today'],
        ]);

        $cleared = ! isset($validated['ak_base']);

        EmployeeCareer::updateOrCreate(['employee_id' => $employee->id], [
            'ak_base' => $validated['ak_base'] ?? null,
            'ak_base_date' => $validated['ak_base_date'] ?? null,
            'ak_base_source' => $cleared ? null : ($isAdmin ? 'admin' : 'pegawai'),
        ]);

        return back()->with('success', $cleared ? 'AK dari PAK dihapus. Angka kembali ke estimasi.' : 'AK dari PAK disimpan.');
    }

    /**
     * A manual AK for one quarter, with a reason. The calculated value stays on record, and the
     * quarter is marked as edited for everyone who reads it (the employee, the PJ, the head).
     */
    public function updateQuarter(Request $request, Employee $employee, string $quarter): RedirectResponse
    {
        $isAdmin = $request->user()->hasRole('admin');
        abort_unless($isAdmin || $employee->user_id === $request->user()->id, 403);
        abort_unless(preg_match('/^\d{4}-[1-4]$/', $quarter) === 1, 404);

        $validated = $request->validate([
            'ak' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $computed = collect($this->calculator->forEmployee($employee->load(['career', 'performanceRatings', 'creditOverrides']))['quarters'] ?? [])
            ->firstWhere('key', $quarter);
        abort_if($computed === null, 422, 'Triwulan ini tidak ada di perhitungan.');

        CreditQuarterOverride::updateOrCreate(['employee_id' => $employee->id, 'quarter' => $quarter], [
            'ak' => $validated['ak'],
            'computed_ak' => $computed['computed_ak'],
            'reason' => $validated['reason'],
            'edited_by' => $request->user()->id,
            'edited_by_role' => $isAdmin ? 'admin' : 'pegawai',
        ]);

        return back()->with('success', 'AK triwulan disimpan dan ditandai diubah manual.');
    }

    public function destroyQuarter(Request $request, Employee $employee, string $quarter): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin') || $employee->user_id === $request->user()->id, 403);

        CreditQuarterOverride::where('employee_id', $employee->id)->where('quarter', $quarter)->delete();

        return back()->with('success', 'AK triwulan kembali ke perhitungan otomatis.');
    }

    /**
     * Teams whose members' AK the user may see.
     *
     * @return Collection<int, Team>
     */
    public static function visibleTeams(Request $request): Collection
    {
        if ($request->user()->hasAnyRole(['admin', 'head'])) {
            return Team::orderBy('name')->get();
        }

        $employee = $request->user()->employee;
        if ($employee === null) {
            return collect();
        }

        return Team::query()
            ->where('leader_id', $employee->id)
            ->orWhereIn('id', $employee->ledTeams()->pluck('teams.id'))
            ->orderBy('name')
            ->get();
    }
}
