<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformancePlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerformancePlan::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');
        $projectId = $request->integer('project_id');

        // Non-admins see RKs of teams they belong to (member or leader). kipApp
        // RKs are team-scoped (no project), so match team_id OR project.team_id.
        $memberTeamIds = $isAdmin
            ? collect()
            : ($user->employee?->teams()->pluck('teams.id') ?? collect());

        $plans = PerformancePlan::with('project.team:id,name', 'team:id,name', 'pic:id,name,display_name')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when(! $isAdmin, function ($q) use ($memberTeamIds) {
                if ($memberTeamIds->isEmpty()) {
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->where(function ($w) use ($memberTeamIds) {
                    $w->whereIn('team_id', $memberTeamIds)
                        ->orWhereHas('project', fn ($pq) => $pq->whereIn('team_id', $memberTeamIds));
                });
            })
            ->orderBy('code')
            ->orderBy('description')
            ->get();

        $projects = $isAdmin
            ? Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id', 'year'])
            : ($memberTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $memberTeamIds)->orderBy('name')->get(['id', 'name', 'team_id', 'year'])
                : collect());

        $canCreate = $user->can('create', PerformancePlan::class);

        return Inertia::render('PerformancePlans/Index', compact('plans', 'projects', 'projectId', 'canCreate'));
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerformancePlan::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $projects = Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id']);
            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        } else {
            $employee = $user->employee;
            $ledTeamIds = $employee
                ? Team::where('leader_id', $employee->id)->pluck('id')
                : collect();

            $projects = $ledTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $ledTeamIds)->orderBy('name')->get(['id', 'name', 'team_id'])
                : collect();

            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        }

        return Inertia::render('PerformancePlans/Create', compact('projects', 'employees', 'isAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformancePlan::class);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'project_id' => ['required', 'exists:projects,id'],
                'code' => ['nullable', 'string', 'max:50'],
                'description' => ['required', 'string'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'period_type' => ['required', Rule::in(['year', 'quarter'])],
                'period' => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        } else {
            $employee = $request->user()->employee;
            $ledTeamIds = Team::where('leader_id', $employee?->id)->pluck('id');
            abort_if(! $employee || $ledTeamIds->isEmpty(), 403, 'Akun belum memimpin tim manapun.');

            $allowedProjectIds = Project::whereIn('team_id', $ledTeamIds)->pluck('id');

            $validated = $request->validate([
                'project_id' => ['required', Rule::in($allowedProjectIds->all())],
                'code' => ['nullable', 'string', 'max:50'],
                'description' => ['required', 'string'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'period_type' => ['required', Rule::in(['year', 'quarter'])],
                'period' => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        }

        PerformancePlan::create($validated);

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil ditambahkan.');
    }

    public function edit(PerformancePlan $performancePlan, Request $request): Response
    {
        $this->authorize('update', $performancePlan);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        $performancePlan->load('project.team:id,name', 'pic:id,name,display_name');

        if ($isAdmin) {
            $projects = Project::with('team:id,name')->orderBy('name')->get(['id', 'name', 'team_id']);
            $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id']);
        } else {
            $employee = $user->employee;
            $ledTeamIds = $employee
                ? Team::where('leader_id', $employee->id)->pluck('id')
                : collect();
            $projects = $ledTeamIds->isNotEmpty()
                ? Project::with('team:id,name')->whereIn('team_id', $ledTeamIds)->orderBy('name')->get(['id', 'name', 'team_id'])
                : collect();
            $teamId = $performancePlan->project->team_id ?? null;
            $employees = $teamId
                ? Employee::where('team_id', $teamId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name', 'team_id'])
                : collect();
        }

        return Inertia::render('PerformancePlans/Edit', compact('performancePlan', 'projects', 'employees', 'isAdmin'));
    }

    public function update(Request $request, PerformancePlan $performancePlan): RedirectResponse
    {
        $this->authorize('update', $performancePlan);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'project_id' => ['required', 'exists:projects,id'],
                'code' => ['nullable', 'string', 'max:50'],
                'description' => ['required', 'string'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'period_type' => ['required', Rule::in(['year', 'quarter'])],
                'period' => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        } else {
            $validated = $request->validate([
                'code' => ['nullable', 'string', 'max:50'],
                'description' => ['required', 'string'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'period_type' => ['required', Rule::in(['year', 'quarter'])],
                'period' => ['nullable', 'integer', 'min:1', 'max:4'],
                'pic_employee_id' => ['nullable', 'exists:employees,id'],
            ]);
        }

        $performancePlan->update($validated);

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil diperbarui.');
    }

    public function destroy(PerformancePlan $performancePlan): RedirectResponse
    {
        $this->authorize('delete', $performancePlan);

        $performancePlan->delete();

        return redirect()->route('performance-plans.index')
            ->with('success', 'Rencana Kinerja berhasil dihapus.');
    }
}
