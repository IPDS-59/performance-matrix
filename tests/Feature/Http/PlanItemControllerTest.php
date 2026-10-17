<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function planSetup(): array
{
    config(['kinetik.kip.tahun' => 2026]);
    $pjUser = staffUser();
    $pj = Employee::factory()->create(['user_id' => $pjUser->id, 'display_name' => 'Sukma']);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $memberUser = User::factory()->create();
    $member = Employee::factory()->create(['user_id' => $memberUser->id]);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi']);

    return [$pjUser, $team, $member, $memberUser, $rk];
}

function planPayload(Team $team, PerformancePlan $rk, array $extra = []): array
{
    return ['team_id' => $team->id, 'performance_plan_id' => $rk->id, 'description' => 'Entri SE2026', 'date_start' => '2026-10-05', 'date_end' => '2026-10-07', ...$extra];
}

it('lets a member plan for themselves and the PJ plan for a member, with a notice', function () {
    [$pjUser, $team, $member, $memberUser, $rk] = planSetup();

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk))->assertSessionHas('success');
    expect(PlanItem::sole())->employee_id->toBe($member->id)->source->toBe('member')->status->toBe('planned');

    $this->actingAs($pjUser)->post(route('plan-items.store'), planPayload($team, $rk, ['employee_id' => $member->id, 'description' => 'Rapat koordinasi ISO']))->assertSessionHas('success');
    expect(PlanItem::where('description', 'Rapat koordinasi ISO')->first())->source->toBe('pj')
        ->and($memberUser->notifications)->toHaveCount(1)
        ->and($memberUser->notifications->first()->data['message'])->toContain('Sukma ditambahkan rencana untuk Anda');
});

it('blocks members from planning for others, and rejects RK of another team', function () {
    [, $team, $member, $memberUser, $rk] = planSetup();
    $other = Employee::factory()->create();
    $other->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, ['employee_id' => $other->id]))->assertForbidden();

    $foreignRk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => Team::factory()->create()->id]);
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $foreignRk))->assertSessionHasErrors('performance_plan_id');
    expect(PlanItem::count())->toBe(0);
});

it('requires a Projek when the team has projects and the RK has none', function () {
    [, $team, , $memberUser, $rk] = planSetup();
    $project = Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK Ketua']);
    $rk->update(['leader_rk' => 'RK Ketua']);
    Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK Ketua']);

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk))->assertSessionHasErrors('project_id');
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, ['project_id' => $project->id]))->assertSessionHas('success');
    expect(PlanItem::sole()->project_id)->toBe($project->id);
});

it('edits and cancels only plans still in the planned state', function () {
    [$pjUser, $team, $member, $memberUser, $rk] = planSetup();
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Awal', 'date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'created_by' => $member->id]);

    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk, ['description' => 'Deskripsi diubah']))->assertSessionHas('success');
    expect($item->fresh()->description)->toBe('Deskripsi diubah');

    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk, ['date_end' => '2026-10-01']))->assertSessionHasErrors('date_end');

    $this->actingAs($pjUser)->delete(route('plan-items.destroy', $item))->assertSessionHas('success');
    expect($item->fresh()->status)->toBe('cancelled');

    $item->update(['status' => 'pushed']);
    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk))->assertStatus(422);
});

it('stores the kipApp fields, target and capaian as numbers', function () {
    [, $team, , $memberUser, $rk] = planSetup();
    $payload = planPayload($team, $rk, ['target' => 12, 'target_unit' => 'blok', 'capaian' => 9.5, 'capaian_note' => 'Blok 1-9 selesai', 'evidence_url' => 'https://drive.example/b', 'progress' => 75]);

    $this->actingAs($memberUser)->post(route('plan-items.store'), $payload)->assertSessionHasNoErrors();

    $item = PlanItem::sole();
    expect($item->capaian)->toBe('9.50')->and($item->capaian_note)->toBe('Blok 1-9 selesai')->and($item->progress)->toBe(75)->and($item->evidence_url)->toBe('https://drive.example/b');

    foreach ([['target' => 'banyak'], ['capaian' => 'sembilan'], ['capaian' => -1], ['evidence_url' => 'bukan-url'], ['progress' => 101]] as $bad) {
        $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, $bad))->assertSessionHasErrors(array_key_first($bad));
    }
    expect(PlanItem::count())->toBe(1);
});

it('validates the date order and the clock time of a jam kegiatan', function () {
    [, $team, , $memberUser, $rk] = planSetup();

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, ['date_end' => '2026-10-01']))->assertSessionHasErrors('date_end');
    // The end must come after the start. A range with times is allowed (the two checkboxes are independent).
    $timed = ['date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'time_start' => '09:00', 'time_end' => '11:30'];
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, [...$timed, 'time_end' => '08:00']))->assertSessionHasErrors('time_end');
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, [...$timed, 'time_end' => null]))->assertSessionHasErrors('time_end');

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, $timed))->assertSessionHasNoErrors();
    expect(PlanItem::sole())->time_start->toStartWith('09:00')->time_end->toStartWith('11:30');

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, [...$timed, 'date_end' => '2026-10-06']))->assertSessionHasNoErrors();
    expect(PlanItem::where('date_end', '2026-10-06')->first())->time_start->toStartWith('09:00');
});

it('saves the result of a kegiatan locally, for the owner or the PJ only', function () {
    [$pjUser, $team, $member, $memberUser, $rk] = planSetup();
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Awal', 'date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'status' => 'done', 'kip_external_id' => '55']);
    Http::fake();

    $this->actingAs($memberUser)->patch(route('plan-items.result', $item), ['capaian' => 4, 'capaian_note' => 'Empat blok', 'evidence_url' => 'https://x.example/a'])->assertSessionHas('success');
    expect($item->fresh())->capaian->toBe('4.00')->capaian_note->toBe('Empat blok')->status->toBe('done');
    Http::assertNothingSent();

    $this->actingAs($memberUser)->patch(route('plan-items.result', $item), ['capaian' => 'x'])->assertSessionHasErrors('capaian');
    $this->actingAs(User::factory()->create())->patch(route('plan-items.result', $item), ['capaian' => 1])->assertStatus(403);
    $this->actingAs($pjUser)->patch(route('plan-items.result', $item), ['capaian' => 5])->assertSessionHas('success');
});
