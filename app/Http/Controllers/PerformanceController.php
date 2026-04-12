<?php

namespace App\Http\Controllers;

use App\Actions\Performance\SavePerformanceBatchAction;
use App\Models\PerformanceReport;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;

        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        // Projects where this employee is a member, ordered by team then project name
        $projects = Project::with([
            'workItems.performanceReports' => fn ($q) => $q->where('period_year', $year)->where('period_month', $month),
            'team:id,name',
        ])
            ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('year', $year)
            ->join('teams', 'teams.id', '=', 'projects.team_id')
            ->orderBy('teams.name')
            ->orderBy('projects.name')
            ->select('projects.*')
            ->get();

        $isTeamLead = Project::where('leader_id', $employee->id)->where('year', $year)->exists();
        $teamProjects = $isTeamLead
            ? Project::with([
                'workItems' => fn ($q) => $q->with([
                    'performanceReports' => fn ($q) => $q
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->with('reporter:id,name,display_name'),
                ]),
                'members:id,name,display_name',
                'team:id,name',
            ])
                ->where('leader_id', $employee->id)
                ->where('year', $year)
                ->join('teams', 'teams.id', 'projects.team_id')
                ->orderBy('teams.name')
                ->orderBy('projects.name')
                ->select('projects.*')
                ->get()
            : collect();

        return Inertia::render('Performance/Index', [
            'employee' => $employee->only('id', 'name', 'display_name'),
            'projects' => $projects,
            'is_team_lead' => $isTeamLead,
            'team_projects' => $teamProjects,
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function storeBatch(Request $request, SavePerformanceBatchAction $action): RedirectResponse
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;

        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $validated = $request->validate([
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'period_year' => ['required', 'integer', 'min:2020'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.work_item_id' => ['required', 'exists:work_items,id'],
            'items.*.realization' => ['required', 'numeric', 'min:0'],
            'items.*.issues' => ['nullable', 'string'],
            'items.*.solutions' => ['nullable', 'string'],
            'items.*.action_plan' => ['nullable', 'string'],
        ]);

        $action->execute(
            reporter: $employee,
            periodMonth: $validated['period_month'],
            periodYear: $validated['period_year'],
            items: $validated['items'],
        );

        return back()->with('success', 'Laporan kinerja berhasil disimpan.');
    }
}
