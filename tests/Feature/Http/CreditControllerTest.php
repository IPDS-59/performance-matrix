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

it('lets employees enter their own PAK value, marked as not checked', function () {
    $user = staffUser();
    $e = Employee::factory()->create(['user_id' => $user->id]);
    careerFor($e);
    $other = Employee::factory()->create();

    $this->actingAs($user)->put(route('credit.base', $e), ['ak_base' => 30, 'ak_base_date' => '2025-12-31'])->assertSessionHasNoErrors();
    expect($e->career()->first()->ak_base_source)->toBe('pegawai');

    $this->actingAs($user)->put(route('credit.base', $other), ['ak_base' => 30, 'ak_base_date' => '2025-12-31'])->assertForbidden();

    $this->actingAs(adminUser())->put(route('credit.base', $e), ['ak_base' => 32, 'ak_base_date' => '2025-12-31']);
    expect($e->career()->first()->ak_base_source)->toBe('admin');

    $this->actingAs($user)->put(route('credit.base', $e), ['ak_base' => null, 'ak_base_date' => null]);
    expect($e->career()->first())->ak_base->toBeNull()->ak_base_source->toBeNull();
});

it('lets a person replace the AK of one quarter, with a reason, and marks it as edited', function () {
    $user = staffUser();
    $e = Employee::factory()->create(['user_id' => $user->id]);
    careerFor($e);

    $this->actingAs($user)->get(route('credit.mine'))
        ->assertInertia(fn ($page) => $page->where('credit.quarters.0.override', null)->where('credit.quarters.0.computed_ak', 3.125));

    $this->actingAs($user)->put(route('credit.quarter', [$e, '2026-1']), ['ak' => 3.75, 'reason' => 'Sesuai PAK TW I 2026'])->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('credit.mine'))
        ->assertInertia(fn ($page) => $page
            ->where('credit.quarters.0.ak', 3.75)
            ->where('credit.quarters.0.computed_ak', 3.125)
            ->where('credit.quarters.0.final', true)
            ->where('credit.quarters.0.override.reason', 'Sesuai PAK TW I 2026')
            ->where('credit.quarters.0.override.role', 'pegawai')
            ->where('credit.earned', 3.75));
});

it('needs a reason, only for a quarter that exists, and only by the person or an admin', function () {
    $user = staffUser();
    $e = Employee::factory()->create(['user_id' => $user->id]);
    careerFor($e);

    $this->actingAs($user)->put(route('credit.quarter', [$e, '2026-1']), ['ak' => 3.75, 'reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($user)->put(route('credit.quarter', [$e, '2025-4']), ['ak' => 1, 'reason' => 'Tidak ada triwulan ini'])->assertUnprocessable();
    $this->actingAs($user)->put(route('credit.quarter', [$e, 'bukan']), ['ak' => 1, 'reason' => 'Format salah'])->assertNotFound();
    $this->actingAs(staffUser())->put(route('credit.quarter', [$e, '2026-1']), ['ak' => 9, 'reason' => 'Bukan saya'])->assertForbidden();

    $this->actingAs(adminUser())->put(route('credit.quarter', [$e, '2026-1']), ['ak' => 3.5, 'reason' => 'Dicek dari dokumen PAK'])->assertSessionHasNoErrors();
    $this->actingAs($user)->get(route('credit.mine'))->assertInertia(fn ($page) => $page->where('credit.quarters.0.override.role', 'admin'));
});

it('returns a quarter to the calculation and shows the edit to a PJ', function () {
    $user = staffUser();
    $pj = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $member = Employee::factory()->create(['is_active' => true, 'user_id' => staffUser()->id]);
    $member->teams()->attach($team->id, ['role' => 'member']);
    careerFor($member);

    $this->actingAs($member->user)->put(route('credit.quarter', [$member, '2026-1']), ['ak' => 4, 'reason' => 'Sesuai PAK'])->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('credit.team'))
        ->assertInertia(fn ($page) => $page->where('rows.0.quarters.0.override.reason', 'Sesuai PAK'));

    $this->actingAs($member->user)->delete(route('credit.quarter.destroy', [$member, '2026-1']))->assertSessionHasNoErrors();
    $this->actingAs($member->user)->get(route('credit.mine'))->assertInertia(fn ($page) => $page->where('credit.quarters.0.override', null)->where('credit.quarters.0.ak', 3.125));
});
