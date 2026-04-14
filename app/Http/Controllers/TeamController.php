<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::orderBy('name')->get(['id', 'name', 'code', 'is_active']);

        return Inertia::render('Teams/Index', compact('teams'));
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('Teams/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:teams,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        Team::create($validated);

        return redirect()->route('teams.index')->with('success', 'Tim berhasil ditambahkan.');
    }

    public function edit(Team $team): Response
    {
        $this->authorize('update', $team);

        return Inertia::render('Teams/Edit', compact('team'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', "unique:teams,code,{$team->id}"],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
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
