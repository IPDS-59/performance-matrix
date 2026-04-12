<?php

namespace App\Http\Controllers;

use App\Actions\Projects\SyncProjectMembersAction;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $year = $request->integer('year', now()->year);
        $teamId = $request->integer('team_id');

        $projects = Project::with('team:id,name', 'leader:id,name,display_name')
            ->withCount('members')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->where('year', $year)
            ->orderBy('name')
            ->get();

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Projects/Index', compact('projects', 'teams', 'year', 'teamId'));
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name']);

        return Inertia::render('Projects/Create', compact('teams', 'employees'));
    }

    public function store(Request $request, SyncProjectMembersAction $syncMembers): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'leader_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'kpi' => ['nullable', 'string'],
            'status' => ['in:active,completed,cancelled'],
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'members' => ['array'],
            'members.*.employee_id' => ['exists:employees,id'],
            'members.*.role' => ['in:leader,member'],
        ]);

        $project = Project::create($validated);

        if (! empty($validated['members'])) {
            $memberMap = collect($validated['members'])
                ->keyBy('employee_id')
                ->map(fn ($m) => ['role' => $m['role']])
                ->all();
            $syncMembers->execute($project, $memberMap);
        }

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil ditambahkan.');
    }

    public function edit(Project $project, Request $request): Response
    {
        $this->authorize('update', $project);

        $user = $request->user();
        $isLeader = ! $user->hasPermissionTo('manage-projects')
            && $user->employee !== null
            && $user->employee->id === $project->leader_id;

        $project->load('members:id,name,display_name', 'team:id,name', 'workItems');
        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name']);

        return Inertia::render('Projects/Edit', compact('project', 'teams', 'employees', 'isLeader'));
    }

    public function update(Request $request, Project $project, SyncProjectMembersAction $syncMembers): RedirectResponse
    {
        $this->authorize('update', $project);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id' => ['required', 'exists:teams,id'],
                'leader_id' => ['nullable', 'exists:employees,id'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'objective' => ['nullable', 'string'],
                'kpi' => ['nullable', 'string'],
                'status' => ['in:active,completed,cancelled'],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'members' => ['array'],
                'members.*.employee_id' => ['exists:employees,id'],
                'members.*.role' => ['in:leader,member'],
            ]);

            $project->update($validated);

            $memberMap = collect($validated['members'] ?? [])
                ->keyBy('employee_id')
                ->map(fn ($m) => ['role' => $m['role']])
                ->all();
            $syncMembers->execute($project, $memberMap);
        } else {
            // Project leader: only allowed to update members
            $validated = $request->validate([
                'members' => ['array'],
                'members.*.employee_id' => ['exists:employees,id'],
                'members.*.role' => ['in:leader,member'],
            ]);

            $memberMap = collect($validated['members'] ?? [])
                ->keyBy('employee_id')
                ->map(fn ($m) => ['role' => $m['role']])
                ->all();
            $syncMembers->execute($project, $memberMap);
        }

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil diperbarui.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil dihapus.');
    }
}
