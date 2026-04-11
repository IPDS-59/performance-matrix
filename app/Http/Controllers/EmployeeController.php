<?php

namespace App\Http\Controllers;

use App\Actions\Employees\LinkEmployeeToUserAction;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
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

        return Inertia::render('Employees/Edit', compact('employee', 'teams', 'users'));
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
}
