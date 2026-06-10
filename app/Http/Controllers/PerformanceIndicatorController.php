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
        $isAdmin = $user->hasPermissionTo('manage-projects');
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

        $teams = $isAdmin
            ? Team::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : Team::whereIn('id', $memberTeamIds)->orderBy('name')->get(['id', 'name']);

        $canCreate = $user->can('create', PerformanceIndicator::class);

        return Inertia::render('PerformanceIndicators/Index', compact('indicators', 'teams', 'year', 'teamId', 'canCreate'));
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PerformanceIndicator::class);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            $employee = $user->employee;
            $teams = $employee
                ? Team::where('leader_id', $employee->id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();
        }

        return Inertia::render('PerformanceIndicators/Create', compact('teams', 'isAdmin'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformanceIndicator::class);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id' => ['required', 'exists:teams,id'],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'code' => ['nullable', 'string', 'max:50'],
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
                'code' => ['nullable', 'string', 'max:50'],
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

    public function edit(PerformanceIndicator $performanceIndicator, Request $request): Response
    {
        $this->authorize('update', $performanceIndicator);

        $user = $request->user();
        $isAdmin = $user->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        } else {
            $teams = Team::where('id', $performanceIndicator->team_id)->get(['id', 'name']);
        }

        return Inertia::render('PerformanceIndicators/Edit', compact('performanceIndicator', 'teams', 'isAdmin'));
    }

    public function update(Request $request, PerformanceIndicator $performanceIndicator): RedirectResponse
    {
        $this->authorize('update', $performanceIndicator);

        $isAdmin = $request->user()->hasPermissionTo('manage-projects');

        if ($isAdmin) {
            $validated = $request->validate([
                'team_id' => ['required', 'exists:teams,id'],
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'code' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'target' => ['nullable', 'numeric'],
                'target_unit' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
            ]);
        } else {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'code' => ['nullable', 'string', 'max:50'],
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
