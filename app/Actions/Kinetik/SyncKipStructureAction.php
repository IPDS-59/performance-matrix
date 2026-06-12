<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Mirror kipApp organisational structure (Tim/Projek/Anggota/Pegawai) into the
 * local master tables, keyed on stable kipApp ids and employee nip_lama.
 *
 * The unit of work is one team ({@see syncTeam()}) so the same logic powers both
 * the CLI/cron full run ({@see execute()}) and the chunked, no-queue web sync
 * (one team per HTTP request, driven by the browser).
 *
 * Ownership rules:
 *  - Teams/Projects are upserted by kip_external_id (adopting an existing
 *    same-named row that has no kip id yet).
 *  - Employees are provisioned by nip_lama; an optional login User is created
 *    with a derived BPS email (kipApp exposes no employee email) + default
 *    password. display_name/full_name are only set on create.
 *  - project_members is kipApp-owned -> full sync. employee_team is shared with
 *    Domain A -> additive syncWithoutDetaching only.
 *
 * @phpstan-type Counts array{teams:int, projects:int, indicators:int, employees_created:int, employees_updated:int, users_created:int, project_member_links:int, team_member_links:int, skipped_no_niplama:int}
 */
class SyncKipStructureAction
{
    /** @var Collection<string, Employee> nip_lama => Employee, cached per team */
    private Collection $employeeCache;

    /** @var array<string, string>|null nip_lama => username, lazily loaded */
    private ?array $usernameMap = null;

    /** @var Counts */
    private array $counts;

    /**
     * Full run over every (or the given) team. Suitable for CLI/cron where there
     * is no execution-time limit.
     *
     * @param  string[]  $timkerjaIds  Explicit team ids; empty => every unit team.
     * @return Counts
     */
    public function execute(KipStructureSource $source, array $timkerjaIds = []): array
    {
        $teamIds = ! empty($timkerjaIds)
            ? collect($timkerjaIds)
            : $source->fetchTeams()->map(fn ($t) => $t->externalId);

        $total = $this->emptyCounts();

        foreach ($teamIds->unique()->filter() as $timkerjaId) {
            $total = $this->mergeCounts($total, $this->syncTeam($source, (string) $timkerjaId));
        }

        return $total;
    }

    /**
     * Sync exactly one team (projects, members, employees, logins). Self-contained
     * so it can run inside a single short HTTP request.
     *
     * @return Counts
     */
    public function syncTeam(KipStructureSource $source, string $timkerjaId): array
    {
        $this->employeeCache = collect();
        $this->counts = $this->emptyCounts();

        $projects = $source->fetchTeamProjects($timkerjaId);

        $team = $this->upsertTeam($timkerjaId, $projects);
        $this->counts['teams'] = 1;

        /** @var Collection<int, Employee> $teamEmployees */
        $teamEmployees = collect();

        // Team leader (kipApp niplamaketua) -> teams.leader_id + leader pivot.
        $leaderNip = $projects->map(fn (KipProjectData $p) => $p->leaderNipLama)->filter()->first();
        $leaderName = $projects->map(fn (KipProjectData $p) => $p->leaderName)->filter()->first();
        $leader = null;
        if ($leaderNip) {
            $leader = $this->resolveByNip((string) $leaderNip)
                ?? ($leaderName ? $this->provisionEmployee(KipMemberData::fromApiRow(['niplama' => $leaderNip, 'nama' => $leaderName])) : null);
        }
        if ($leader) {
            $team->leader_id = $leader->id;
            $team->save();
            $team->members()->syncWithoutDetaching([$leader->id => ['role' => 'leader']]);
            $teamEmployees->push($leader);
            $this->counts['team_member_links']++;
        }

        foreach ($projects as $projectData) {
            $indicator = $this->upsertIndicator($team, $projectData);
            $project = $this->upsertProject($team, $projectData, $leader?->id, $indicator?->id);
            $this->counts['projects']++;

            $members = $this->provisionEmployees($projectData->members);
            $project->members()->sync(
                $members->mapWithKeys(fn (Employee $e) => [
                    $e->id => ['role' => ($leader && $e->id === $leader->id) ? 'leader' : 'member'],
                ])->all()
            );
            // Ensure the team leader is recorded as project 'leader' (the dashboard
            // "as Ketua" ranking counts project_members.role = leader), even if not
            // listed among the project's anggota.
            if ($leader) {
                $project->members()->syncWithoutDetaching([$leader->id => ['role' => 'leader']]);
            }
            $teamEmployees = $teamEmployees->concat($members);
            $this->counts['project_member_links'] += $members->count();
        }

        // Team roster (timkerja/anggota) — provision; pivot handled below.
        $teamEmployees = $teamEmployees->concat(
            $this->provisionEmployees($source->fetchTeamMembers($timkerjaId))
        );

        // Everyone working in this team (leader + project members + roster) is a
        // team member, so team-scoped views (recaps) resolve their membership.
        // Additive (syncWithoutDetaching) and never downgrades the leader's role.
        $memberLinks = $teamEmployees
            ->reject(fn (Employee $e) => $leader && $e->id === $leader->id)
            ->unique('id')
            ->mapWithKeys(fn (Employee $e) => [$e->id => ['role' => 'member']])
            ->all();
        if (! empty($memberLinks)) {
            $team->members()->syncWithoutDetaching($memberLinks);
            $this->counts['team_member_links'] += count($memberLinks);
        }

        $this->assignHomeTeam($team, $teamEmployees);

        return $this->counts;
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

    private function upsertProject(Team $team, KipProjectData $data, ?int $leaderId, ?int $indicatorId): Project
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
        if ($indicatorId) {
            $project->performance_indicator_id = $indicatorId;
        }
        $project->save();

        return $project;
    }

    /**
     * IKU = the team leader's RK for the project (rkketuaid / rencanakinerjaketua).
     */
    private function upsertIndicator(Team $team, KipProjectData $data): ?PerformanceIndicator
    {
        if ($data->ikuExternalId === null || $data->ikuName === null) {
            return null;
        }

        // Dedup by (team_id, name): reuse any existing indicator with the same name
        // in this team, regardless of kip_external_id (many projects share the same
        // rencanakinerjaketua text but carry different rkketuaids).
        $indicator = PerformanceIndicator::where('team_id', $team->id)
            ->where('name', $data->ikuName)
            ->first()
            ?? PerformanceIndicator::where('kip_external_id', $data->ikuExternalId)->first();

        $isNew = $indicator === null;
        if ($isNew) {
            $indicator = new PerformanceIndicator(['year' => (int) config('kinetik.kip.tahun')]);
            $indicator->kip_external_id = $data->ikuExternalId;
        }

        $indicator->team_id = $team->id;
        $indicator->name = $data->ikuName;
        $indicator->save();

        if ($isNew) {
            $this->counts['indicators']++;
        }

        return $indicator;
    }

    /**
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
            $this->counts['skipped_no_niplama']++;

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

        $isNew ? $this->counts['employees_created']++ : $this->counts['employees_updated']++;

        $this->ensureLogin($employee);

        $this->employeeCache->put($m->nipLama, $employee);

        return $employee;
    }

    /**
     * Create or upgrade a login User for an employee. When the username map
     * provides a real SSO email (username@bps.go.id), it is preferred over the
     * derived firstname@bpssulteng.id — and an existing derived login is
     * upgraded to the real email on re-sync.
     */
    private function ensureLogin(Employee $employee): void
    {
        if (! config('kinetik.kip.create_logins')) {
            return;
        }

        $realEmail = $this->realEmailFor($employee->nip_lama);

        // Upgrade an existing login to the real email when it differs and is not
        // already taken by another user.
        if ($employee->user_id !== null) {
            if ($realEmail !== null) {
                $user = User::find($employee->user_id);
                if ($user && $user->email !== $realEmail && ! User::where('email', $realEmail)->where('id', '!=', $user->id)->exists()) {
                    $user->email = $realEmail;
                    $user->save();
                }
            }

            return;
        }

        // Determine the email to use for new login creation.
        $email = $realEmail !== null && ! User::where('email', $realEmail)->exists()
            ? $realEmail
            : $this->deriveEmail($employee->name);

        $user = User::create([
            'name' => $employee->display_name ?? $employee->name,
            'email' => $email,
            'password' => Hash::make((string) config('kinetik.kip.default_password', 'password')),
            'role' => 'staff',
        ]);

        // Spatie role is supplementary to the users.role column; only assign it
        // when the role has been seeded (prod always has it).
        if (method_exists($user, 'assignRole') && Role::where('name', 'staff')->exists()) {
            $user->assignRole('staff');
        }

        $employee->user_id = $user->id;
        $employee->save();

        $this->counts['users_created']++;
    }

    /**
     * Resolve a real SSO email from the username map for a given nip_lama.
     * The map is loaded lazily and cached for the action's lifetime.
     */
    private function realEmailFor(?string $nipLama): ?string
    {
        if ($nipLama === null || $nipLama === '') {
            return null;
        }

        if ($this->usernameMap === null) {
            $path = config('kinetik.kip.username_map_path');
            $this->usernameMap = (is_string($path) && is_file($path))
                ? (json_decode((string) file_get_contents($path), true) ?: [])
                : [];
        }

        $username = $this->usernameMap[$nipLama] ?? null;
        if (! $username) {
            return null;
        }

        $domain = (string) config('kinetik.kip.real_email_domain', 'bps.go.id');

        return strtolower($username).'@'.$domain;
    }

    /**
     * Derive a login email like UserSeeder: firstname@<domain>, appending the
     * second name (then a counter) only when the firstname is already taken.
     */
    private function deriveEmail(string $name): string
    {
        $clean = fn (string $s): string => (string) preg_replace('/[^a-z0-9]/', '', Str::lower($s));
        $first = $clean(Str::before($name, ' ')) ?: 'pegawai';
        $second = $clean(Str::before(Str::after($name, ' '), ' '));
        $domain = (string) config('kinetik.kip.email_domain', 'bpssulteng.id');

        if (! User::where('email', "{$first}@{$domain}")->exists()) {
            return "{$first}@{$domain}";
        }

        $withSecond = $second !== '' ? "{$first}{$second}" : $first;
        if (! User::where('email', "{$withSecond}@{$domain}")->exists()) {
            return "{$withSecond}@{$domain}";
        }

        $i = 2;
        while (User::where('email', "{$withSecond}{$i}@{$domain}")->exists()) {
            $i++;
        }

        return "{$withSecond}{$i}@{$domain}";
    }

    private function resolveByNip(string $nipLama): ?Employee
    {
        return $this->employeeCache->get($nipLama)
            ?? Employee::where('nip_lama', $nipLama)->first();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private function assignHomeTeam(Team $team, Collection $employees): void
    {
        foreach ($employees->unique('id') as $employee) {
            if ($employee->team_id === null) {
                $employee->team_id = $team->id;
                $employee->save();
                // syncWithoutDetaching (not updateExistingPivot) so the row is
                // created when missing, preserving any existing role.
                $team->members()->syncWithoutDetaching([$employee->id => ['is_primary' => true]]);
            }
        }
    }

    /**
     * @return Counts
     */
    private function emptyCounts(): array
    {
        return [
            'teams' => 0,
            'projects' => 0,
            'indicators' => 0,
            'employees_created' => 0,
            'employees_updated' => 0,
            'users_created' => 0,
            'project_member_links' => 0,
            'team_member_links' => 0,
            'skipped_no_niplama' => 0,
        ];
    }

    /**
     * @param  Counts  $a
     * @param  Counts  $b
     * @return Counts
     */
    private function mergeCounts(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] += $v;
        }

        return $a;
    }
}
