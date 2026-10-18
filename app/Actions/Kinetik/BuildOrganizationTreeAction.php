<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;

/**
 * The organisation as a tree for the Struktur Organisasi page: the satker and its head, then every
 * team with its leader and members. Only active employees show. One person can sit in several teams.
 */
class BuildOrganizationTreeAction
{
    public const SATKER = 'BPS Provinsi Sulawesi Tengah';

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $head = User::query()->where('role', 'head')->with('employee')->first()?->employee;

        $teams = Team::query()
            ->where('is_active', true)
            ->with(['members' => fn ($q) => $q->where('employees.is_active', true)->orderBy('employees.name')])
            ->orderBy('name')
            ->get()
            ->map(function (Team $team) {
                $members = $team->members->map(fn (Employee $e) => [...$this->person($e), 'role' => $e->pivot->role === 'leader' || $e->id === $team->leader_id ? 'leader' : 'member']);

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'leader' => $members->firstWhere('role', 'leader'),
                    'members' => $members->where('role', 'member')->values(),
                ];
            })->values();

        return [
            'satker' => self::SATKER,
            'head' => $head ? $this->person($head) : null,
            'employee_count' => Employee::where('is_active', true)->count(),
            'teams' => $teams,
        ];
    }

    /** @return array{id: int, name: string, position: string|null} */
    private function person(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->display_name ?: $employee->name,
            'position' => $employee->position,
        ];
    }
}
