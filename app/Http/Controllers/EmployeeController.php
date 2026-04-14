<?php

namespace App\Http\Controllers;

use App\Actions\Employees\LinkEmployeeToUserAction;
use App\Http\Requests\StoreMutationRequest;
use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeTeamHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::with('team:id,name', 'educations')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'team_id', 'employee_number', 'position', 'office', 'is_active', 'user_id']);

        return Inertia::render('Employees/Index', compact('employees'));
    }

    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Employees/Create', compact('teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:30', 'unique:employees,employee_number'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        Employee::create($validated);

        return redirect()->route('employees.index')->with('success', 'Pegawai berhasil ditambahkan.');
    }

    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $users = User::whereDoesntHave('employee')->orderBy('name')->get(['id', 'name', 'email']);
        $employee->load('educations', 'team:id,name');

        $mutations = $employee->teamHistories()
            ->with('team:id,name')
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('Employees/Edit', compact('employee', 'teams', 'users', 'mutations'));
    }

    public function update(Request $request, Employee $employee, LinkEmployeeToUserAction $linkAction): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:30', "unique:employees,employee_number,{$employee->id}"],
            'team_id' => ['nullable', 'exists:teams,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $newUserId = $validated['user_id'] ?? null;
        $wasLinked = $employee->user_id !== $newUserId && $newUserId !== null;

        $employee->update($validated);

        if ($wasLinked) {
            $user = User::findOrFail($newUserId);
            $linkAction->execute($employee, $user);
        }

        return redirect()->route('employees.index')->with('success', 'Pegawai berhasil diperbarui.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Pegawai berhasil dihapus.');
    }

    public function storeMutation(StoreMutationRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();

        // Close the current open history entry for this employee.
        EmployeeTeamHistory::where('employee_id', $employee->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => $validated['started_at']]);

        // Record the new team assignment.
        EmployeeTeamHistory::create([
            'employee_id' => $employee->id,
            'team_id' => $validated['team_id'],
            'started_at' => $validated['started_at'],
            'notes' => $validated['notes'] ?? null,
            'ended_at' => null,
        ]);

        // Update the employee's current team.
        $employee->update(['team_id' => $validated['team_id']]);

        return redirect()->back()->with('success', 'Mutasi pegawai berhasil disimpan.');
    }

    public function mutationHistory(Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $mutations = $employee->teamHistories()
            ->with('team:id,name')
            ->orderByDesc('started_at')
            ->get();

        return response()->json($mutations);
    }

    public function storeEducation(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'degree_front' => ['nullable', 'string', 'max:30'],
            'degree_back' => ['nullable', 'string', 'max:30'],
            'institution' => ['required', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'graduated_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 5)],
            'is_highest' => ['boolean'],
        ]);

        if (! empty($validated['is_highest'])) {
            $employee->educations()->update(['is_highest' => false]);
        }

        $employee->educations()->create($validated);

        $this->syncHighestEducationDisplay($employee);

        return redirect()->back()->with('success', 'Riwayat pendidikan berhasil ditambahkan.');
    }

    public function updateEducation(Request $request, Employee $employee, EmployeeEducation $education): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($education->employee_id === $employee->id, 403);

        $validated = $request->validate([
            'degree_front' => ['nullable', 'string', 'max:30'],
            'degree_back' => ['nullable', 'string', 'max:30'],
            'institution' => ['required', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'graduated_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 5)],
            'is_highest' => ['boolean'],
        ]);

        if (! empty($validated['is_highest'])) {
            $employee->educations()->where('id', '!=', $education->id)->update(['is_highest' => false]);
        }

        $education->update($validated);

        $this->syncHighestEducationDisplay($employee);

        return redirect()->back()->with('success', 'Riwayat pendidikan berhasil diperbarui.');
    }

    public function destroyEducation(Employee $employee, EmployeeEducation $education): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($education->employee_id === $employee->id, 403);

        $education->delete();

        $this->syncHighestEducationDisplay($employee);

        return redirect()->back()->with('success', 'Riwayat pendidikan berhasil dihapus.');
    }

    private function syncHighestEducationDisplay(Employee $employee): void
    {
        $highest = $employee->educations()->where('is_highest', true)->first();
        if (! $highest) {
            $highest = $employee->educations()->orderByDesc('graduated_year')->first();
        }

        $namePart = $highest?->degree_front
            ? $highest->degree_front.' '.$employee->name
            : $employee->name;

        $displayName = $highest?->degree_back
            ? $namePart.', '.$highest->degree_back
            : $namePart;

        $employee->update(['display_name' => $displayName]);
    }
}
