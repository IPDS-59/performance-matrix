<?php

use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\KipPerformanceRating;
use App\Models\Team;

function careerFor(Employee $e, string $golongan = 'III/a'): void
{
    EmployeeCareer::create([
        'employee_id' => $e->id, 'jabatan' => 'Statistisi Ahli Pertama', 'golongan' => $golongan,
        'golongan_since' => '2024-01-01', 'level_since' => '2024-01-01', 'level_start_golongan' => 'III/a',
    ]);
    KipPerformanceRating::create([
        'employee_id' => $e->id, 'kip_skp_id' => "s{$e->id}", 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
        'jabatan' => 'Statistisi Ahli Pertama', 'predikat' => 'Baik', 'status' => 'Dinilai',
    ]);
}

it('shows each person their own Angka Kredit', function () {
    $user = staffUser();
    $e = Employee::factory()->create(['user_id' => $user->id]);
    careerFor($e);

    $this->actingAs($user)->get(route('credit.mine'))
        ->assertInertia(fn ($page) => $page
            ->component('Credit/Mine')
            ->where('credit.employee_id', $e->id)
            ->where('credit.kind', 'pangkat')
            ->where('credit.next_label', 'III/b'));
});

it('shows a PJ only the members of the teams they lead', function () {
    $user = staffUser();
    $pj = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $member = Employee::factory()->create(['is_active' => true]);
    $member->teams()->attach($team->id, ['role' => 'member']);
    $outsider = Employee::factory()->create(['is_active' => true]);
    $outsider->teams()->attach(Team::factory()->create()->id, ['role' => 'member']);
    careerFor($member);
    careerFor($outsider);

    $this->actingAs($user)->get(route('credit.team'))
        ->assertInertia(fn ($page) => $page
            ->component('Credit/Team')
            ->where('canEditBase', false)
            ->has('teams', 1)
            ->where('rows', fn ($rows) => collect($rows)->pluck('employee_id')->contains($member->id)
                && ! collect($rows)->pluck('employee_id')->contains($outsider->id)));
});

it('forbids the team page for a member who leads no team', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('credit.team'))->assertForbidden();
});

it('lets only the admin set the AK from the last PAK', function () {
    $e = Employee::factory()->create();
    careerFor($e);

    $this->actingAs(staffUser())->put(route('credit.base', $e), ['ak_base' => 40, 'ak_base_date' => '2025-12-31'])->assertForbidden();

    $this->actingAs(adminUser())->put(route('credit.base', $e), ['ak_base' => 40, 'ak_base_date' => '2025-12-31'])->assertSessionHasNoErrors();
    expect($e->career()->first())->ak_base->toEqual(40)->and($e->career()->first()->ak_base_date->toDateString())->toBe('2025-12-31');

    $this->actingAs(adminUser())->put(route('credit.base', $e), ['ak_base' => 40])->assertSessionHasErrors('ak_base_date');
});
