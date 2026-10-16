<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-teams');

        $teams = Team::orderBy('name')->get(['id', 'name', 'code', 'description', 'is_active', 'leader_id']);

        $manageableTeamIds = $isAdmin
            ? $teams->pluck('id')->all()
            : ($user->employee
                ? $teams->where('leader_id', $user->employee->id)->pluck('id')->all()
                : []);

        $employees = Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'display_name']);

        return Inertia::render('Teams/Index', compact('teams', 'manageableTeamIds', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:teams,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'leader_id' => ['nullable', 'exists:employees,id'],
        ]);

        Team::create($validated);

        return redirect()->route('teams.index')->with('success', 'Tim berhasil ditambahkan.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', "unique:teams,code,{$team->id}"],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'leader_id' => ['nullable', 'exists:employees,id'],
        ]);

        $team->update($validated);

        return redirect()->route('teams.index')->with('success', 'Tim berhasil diperbarui.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return redirect()->route('teams.index')->with('success', 'Tim berhasil dihapus.');
    }
}
