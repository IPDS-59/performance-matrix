<?php

namespace App\Http\Controllers;

use App\Models\PerformanceIndicator;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceIndicatorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerformanceIndicator::class);

        $user = $request->user();
        // The head reads every team's data; edit rights still come from the policies.
        $isAdmin = $user->hasPermissionTo('manage-projects') || $user->hasRole('head');
        $year = $request->integer('year', now()->year);
        $teamId = $request->integer('team_id');

        // Non-admins see IKU across all teams they belong to (not just home team).
        $memberTeamIds = $isAdmin
            ? collect()
            : ($user->employee?->teams()->pluck('teams.id') ?? collect());

        $indicators = PerformanceIndicator::with('team:id,name')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when(! $isAdmin && ! $teamId, fn ($q) => $q->whereIn('team_id', $memberTeamIds))
            ->where('year', $year)
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $indicators = $indicators->map(fn ($indicator) => array_merge($indicator->toArray(), [
            'can_update' => $user->can('update', $indicator),
            'can_delete' => $user->can('delete', $indicator),
        ]));

        $teams = $isAdmin
            ? Team::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Team::whereIn('id', $memberTeamIds)->orderBy('name')->get(['id', 'name']);

        $canCreate = $user->can('create', PerformanceIndicator::class);

        // Teams the form popup can pick: admins any active team, a PJ the teams they lead.
        $manager = $user->hasPermissionTo('manage-projects');
        $formTeams = $manager
            ? Team::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Team::where('leader_id', $user->employee?->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('PerformanceIndicators/Index', compact('indicators', 'teams', 'year', 'teamId', 'canCreate', 'formTeams', 'manager'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformanceIndicator::class);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id' => ['required', 'exists:teams,id'],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'name' => ['required', 'string', 'max:255'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $employee = $request->user()->employee;
            $ledTeamIds = Team::where('leader_id', $employee?->id)->pluck('id');
            abort_if(! $employee || $ledTeamIds->isEmpty(), 403, 'Akun belum memimpin tim manapun.');

            $validated = $request->validate([
                'team_id' => ['required', Rule::in($ledTeamIds->all())],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'name' => ['required', 'string', 'max:255'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        }

        PerformanceIndicator::create($validated);

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil ditambahkan.');
    }

    public function update(Request $request, PerformanceIndicator $performanceIndicator): RedirectResponse
    {
        $this->authorize('update', $performanceIndicator);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id' => ['required', 'exists:teams,id'],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'name' => ['required', 'string', 'max:255'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'name' => ['required', 'string', 'max:255'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        }

        $performanceIndicator->update($validated);

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil diperbarui.');
    }

    public function destroy(PerformanceIndicator $performanceIndicator): RedirectResponse
    {
        $this->authorize('delete', $performanceIndicator);

        $performanceIndicator->delete();

        return redirect()->route('performance-indicators.index')
            ->with('success', 'IKU berhasil dihapus.');
    }
}
