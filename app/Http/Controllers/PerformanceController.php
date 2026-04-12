<?php

namespace App\Http\Controllers;

use App\Actions\Performance\SavePerformanceBatchAction;
use App\Models\Employee;
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

        return Inertia::render('Performance/Index', [
            'employee' => $employee->only('id', 'name', 'display_name'),
            'projects' => $this->personalProjects($employee, $year, $month),
            'is_team_lead' => Project::where('leader_id', $employee->id)->where('year', $year)->exists(),
            'team_projects' => $this->teamProjects($employee, $year, $month),
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }

    public function storeBatch(Request $request, SavePerformanceBatchAction $action): RedirectResponse
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;
        abort_if(! $employee, 403);

        $validated = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
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

    // ── Private query helpers ──────────────────────────────────────────────

    private function personalProjects(Employee $employee, int $year, int $month)
    {
        return Project::with([
            'workItems' => fn ($q) => $q
                ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
                ->with([
                    'assignments' => fn ($q) => $q->where('employee_id', $employee->id),
                    'performanceReports' => fn ($q) => $q
                        ->where('period_year', $year)
                        ->where('reported_by', $employee->id)
                        ->with('attachments'),
                ]),
            'team:id,name',
        ])
            ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('year', $year)
            ->join('teams', 'teams.id', '=', 'projects.team_id')
            ->orderBy('teams.name')
            ->orderBy('projects.name')
            ->select('projects.*')
            ->get()
            ->map(fn ($project) => $this->inlineAssignmentTarget($project, $employee));
    }

    /**
     * Replace work_item.target / target_unit with the employee's assignment values
     * so the frontend sees a single flat target without needing to know about assignments.
     */
    private function inlineAssignmentTarget($project, Employee $employee)
    {
        $project->workItems->transform(function ($wi) {
            $assignment = $wi->assignments->first();
            $wi->target = $assignment?->target ?? $wi->target;
            $wi->target_unit = $assignment?->target_unit ?? $wi->target_unit;
            unset($wi->assignments);

            return $wi;
        });

        return $project;
    }

    private function teamProjects(Employee $employee, int $year, int $month)
    {
        $isTeamLead = Project::where('leader_id', $employee->id)->where('year', $year)->exists();

        if (! $isTeamLead) {
            return collect();
        }

        return Project::with([
            'workItems' => fn ($q) => $q->with([
                'assignments.employee:id,name,display_name',
                'performanceReports' => fn ($q) => $q
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->with([
                        'reporter:id,name,display_name',
                        'attachments.reviewer:id,name,display_name',
                    ]),
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
            ->get();
    }
}
