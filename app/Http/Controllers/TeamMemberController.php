<?php

namespace App\Http\Controllers;

use App\Actions\Teams\SyncTeamMembersAction;
use App\Http\Requests\UpdateTeamMembersRequest;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    public function edit(Team $team): Response
    {
        $this->authorize('manageMembers', $team);

        $team->load('members:id,name,display_name');

        $employees = Employee::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        return Inertia::render('Teams/Members', compact('team', 'employees'));
    }

    public function update(UpdateTeamMembersRequest $request, Team $team, SyncTeamMembersAction $syncMembers): RedirectResponse
    {
        $memberMap = collect($request->validated()['members'] ?? [])
            ->keyBy('employee_id')
            ->map(fn ($m) => [
                'role' => $m['role'],
                'is_primary' => (bool) ($m['is_primary'] ?? false),
            ])
            ->all();

        $syncMembers->execute($team, $memberMap);

        return redirect()->route('teams.index')->with('success', 'Anggota tim berhasil diperbarui.');
    }
}
