<?php

use App\Actions\Kinetik\RemindMonthlyCapaianAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

function capaianMember(Team $team, bool $leader = false): Employee
{
    $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id, 'is_active' => true]);
    $team->members()->attach($employee->id, ['role' => $leader ? 'leader' : 'member', 'is_primary' => true]);
    if ($leader) {
        $team->update(['leader_id' => $employee->id]);
    }

    return $employee;
}

function capaianItem(Employee $e, Team $team, string $date, ?float $target, ?float $capaian): void
{
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id]);
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $e->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan '.$date.rand(1, 999), 'date_start' => $date, 'date_end' => $date, 'target' => $target, 'capaian' => $capaian, 'status' => 'done', 'source' => 'kipapp']);
}

it('reminds the members with unfilled kegiatan of last month once, and tells the PJ who', function () {
    $team = Team::factory()->create(['name' => 'Tim Uji']);
    $pj = capaianMember($team, true);
    $late = capaianMember($team);
    $done = capaianMember($team);
    capaianItem($late, $team, '2026-10-05', null, null);
    capaianItem($late, $team, '2026-10-06', 5, null);
    capaianItem($late, $team, '2026-10-07', 5, 5);       // filled: not counted
    capaianItem($late, $team, '2026-09-30', null, null); // another month
    capaianItem($done, $team, '2026-10-05', 5, 5);

    $sent = app(RemindMonthlyCapaianAction::class)->execute(Carbon::parse('2026-11-02 09:00'));

    expect($sent)->toBe(['members' => 1, 'pj' => 1]);
    $note = $late->user->notifications->first();
    expect($note->data['type'])->toBe('capaian_reminder')->and($note->data['message'])->toContain('2 kegiatan Anda di Oktober 2026')
        ->and($note->data['url'])->toContain('month=2026-10')
        ->and($done->user->notifications)->toHaveCount(0)
        ->and($pj->user->notifications->first()->data['message'])->toContain('1 anggota Tim Uji')->toContain('(2)');

    expect(app(RemindMonthlyCapaianAction::class)->execute(Carbon::parse('2026-11-03 09:00')))->toBe(['members' => 0, 'pj' => 0]);
});
