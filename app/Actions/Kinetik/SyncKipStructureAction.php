<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Mirror kipApp organisational structure (Tim/Projek/Anggota/Pegawai) into the
 * local master tables, keyed on stable kipApp ids and employee nip_lama.
 *
 * Ownership rules:
 *  - Teams/Projects are upserted by kip_external_id (adopting an existing
 *    same-named row that has no kip id yet).
 *  - Employees are provisioned by nip_lama: created if missing, otherwise
 *    refreshed (name/nip_baru/position). display_name/full_name are only set on
 *    create so an existing write-through display_name is preserved.
 *  - project_members is treated as kipApp-owned -> full sync (mirror exactly).
 *  - employee_team is shared with Domain A (home-team / is_primary) -> additive
 *    syncWithoutDetaching only, never detaching.
 *
 * @phpstan-type Summary array{teams:int, projects:int, employees_created:int, employees_updated:int, project_member_links:int, team_member_links:int, skipped_no_niplama:int}
 */
class SyncKipStructureAction
{
    /** @var Collection<string, Employee> nip_lama => Employee, cached across the run */
    private Collection $employeeCache;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    /**
     * @param  string[]  $timkerjaIds  Explicit team ids to sync; empty => enumerate
     *                                 every team of the configured unit kerja.
     * @return Summary
     */
    public function execute(KipStructureSource $source, array $timkerjaIds = []): array
    {
        $this->employeeCache = collect();
        $this->created = $this->updated = $this->skipped = 0;

        $teamIds = ! empty($timkerjaIds)
            ? collect($timkerjaIds)
            : $source->fetchTeams()->map(fn ($t) => $t->externalId);

        $summary = [
            'teams' => 0,
            'projects' => 0,
            'project_member_links' => 0,
            'team_member_links' => 0,
        ];

        foreach ($teamIds->unique()->filter() as $timkerjaId) {
            $projects = $source->fetchTeamProjects((string) $timkerjaId);

            $team = $this->upsertTeam((string) $timkerjaId, $projects);
            $summary['teams']++;

            /** @var Collection<int, Employee> $teamEmployees */
            $teamEmployees = collect();

            // Team leader (kipApp niplamaketua) -> teams.leader_id + leader pivot.
            $leaderNip = $projects->map(fn (KipProjectData $p) => $p->leaderNipLama)->filter()->first();
            $leader = $leaderNip ? $this->resolveByNip((string) $leaderNip) : null;
            if ($leader) {
                $team->leader_id = $leader->id;
                $team->save();
                $team->members()->syncWithoutDetaching([$leader->id => ['role' => 'leader']]);
                $teamEmployees->push($leader);
                $summary['team_member_links']++;
            }

            foreach ($projects as $projectData) {
                $project = $this->upsertProject($team, $projectData, $leader?->id);
                $summary['projects']++;

                $members = $this->provisionEmployees($projectData->members);
                $project->members()->sync(
                    $members->mapWithKeys(fn (Employee $e) => [$e->id => ['role' => 'member']])->all()
                );
                $teamEmployees = $teamEmployees->concat($members);
                $summary['project_member_links'] += $members->count();
            }

            // Team members pivot (additive). Never downgrade the leader's role.
            $teamMembers = $this->provisionEmployees($source->fetchTeamMembers((string) $timkerjaId))
                ->reject(fn (Employee $e) => $leader && $e->id === $leader->id);
            if ($teamMembers->isNotEmpty()) {
                $team->members()->syncWithoutDetaching(
                    $teamMembers->mapWithKeys(fn (Employee $e) => [$e->id => ['role' => 'member']])->all()
                );
                $teamEmployees = $teamEmployees->concat($teamMembers);
                $summary['team_member_links'] += $teamMembers->count();
            }

            // Home team (employees.team_id) from kipApp structure: first team wins.
            $this->assignHomeTeam($team, $teamEmployees);
        }

        return $summary + [
            'employees_created' => $this->created,
            'employees_updated' => $this->updated,
            'skipped_no_niplama' => $this->skipped,
        ];
    }

    /**
     * @param  Collection<int, KipProjectData>  $projects
     */
    private function upsertTeam(string $externalId, Collection $projects): Team
    {
        $name = $projects->map(fn (KipProjectData $p) => $p->teamName)->filter()->first() ?? "Tim {$externalId}";

        $team = Team::where('kip_external_id', $externalId)->first()
            ?? Team::whereNull('kip_external_id')->where('name', $name)->first()
            ?? new Team(['code' => 'KIP-'.$externalId, 'is_active' => true]);

        $team->kip_external_id = $externalId;
        $team->name = $name;
        $team->save();

        return $team;
    }

    private function upsertProject(Team $team, KipProjectData $data, ?int $leaderId): Project
    {
        $project = Project::where('kip_external_id', $data->externalId)->first()
            ?? Project::whereNull('kip_external_id')
                ->where('team_id', $team->id)
                ->where('name', $data->name)
                ->first()
            ?? new Project(['status' => 'active', 'year' => (int) config('kinetik.kip.tahun')]);

        $project->kip_external_id = $data->externalId;
        $project->team_id = $team->id;
        $project->name = $data->name !== '' ? $data->name : "Projek {$data->externalId}";
        if ($leaderId) {
            $project->leader_id = $leaderId;
        }
        $project->save();

        return $project;
    }

    /**
     * Create or refresh employees from kipApp member rows, returning the matched
     * Employee models (deduped by nip_lama). Rows without a niplama are skipped.
     *
     * @param  Collection<int, KipMemberData>  $members
     * @return Collection<int, Employee>
     */
    private function provisionEmployees(Collection $members): Collection
    {
        return $members
            ->map(fn (KipMemberData $m) => $this->provisionEmployee($m))
            ->filter()
            ->unique('id')
            ->values();
    }

    private function provisionEmployee(KipMemberData $m): ?Employee
    {
        if ($m->nipLama === '') {
            $this->skipped++;

            return null;
        }

        if ($cached = $this->employeeCache->get($m->nipLama)) {
            return $cached;
        }

        $employee = Employee::where('nip_lama', $m->nipLama)->first();
        $isNew = $employee === null;
        $employee ??= new Employee(['nip_lama' => $m->nipLama]);

        if ($m->name !== '') {
            $employee->name = $m->name;
        }
        if ($m->nipBaru) {
            $employee->nip_baru = $m->nipBaru;
        }
        if ($m->jabatanName) {
            $employee->position = $m->jabatanName;
        }
        if ($isNew) {
            $employee->full_name = $m->name;
            $employee->display_name = $m->name;
            $employee->is_active = true;
        }
        $employee->save();

        $isNew ? $this->created++ : $this->updated++;
        $this->employeeCache->put($m->nipLama, $employee);

        return $employee;
    }

    private function resolveByNip(string $nipLama): ?Employee
    {
        return $this->employeeCache->get($nipLama)
            ?? Employee::where('nip_lama', $nipLama)->first();
    }

    /**
     * Set the denormalised home team (employees.team_id + is_primary pivot) for
     * employees that don't have one yet. First synced team wins; never overrides
     * an existing home team (e.g. set manually in Domain A).
     *
     * @param  Collection<int, Employee>  $employees
     */
    private function assignHomeTeam(Team $team, Collection $employees): void
    {
        foreach ($employees->unique('id') as $employee) {
            if ($employee->team_id === null) {
                $employee->team_id = $team->id;
                $employee->save();
                $team->members()->updateExistingPivot($employee->id, ['is_primary' => true]);
            }
        }
    }
}
