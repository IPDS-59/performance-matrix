<?php

use App\Models\Employee;
use App\Models\Team;

it('shows the tree of satker, head, teams, leaders and active members to any signed in user', function () {
    $team = Team::factory()->create(['name' => 'KEUANGAN']);
    $leader = Employee::factory()->create(['name' => 'Ketua Satu', 'display_name' => 'Ketua Satu']);
    $member = Employee::factory()->create(['name' => 'Anggota Satu', 'display_name' => 'Anggota Satu']);
    $gone = Employee::factory()->create(['name' => 'Sudah Pindah', 'display_name' => 'Sudah Pindah', 'is_active' => false]);
    $team->update(['leader_id' => $leader->id]);
    $team->members()->attach([$leader->id => ['role' => 'leader'], $member->id => ['role' => 'member'], $gone->id => ['role' => 'member']]);

    $head = headUser();
    $head->update(['role' => 'head']);
    $headEmployee = Employee::factory()->create(['user_id' => $head->id, 'name' => 'Kepala Satker', 'display_name' => 'Kepala Satker']);

    $this->actingAs(staffUser())->get(route('organization.index'))->assertInertia(fn ($page) => $page
        ->component('Organization/Index')
        ->where('tree.satker', 'BPS Provinsi Sulawesi Tengah')
        ->where('tree.head.id', $headEmployee->id)
        ->where('tree.teams.0.name', 'KEUANGAN')
        ->where('tree.teams.0.leader.name', 'Ketua Satu')
        ->where('tree.teams.0.members', fn ($members) => collect($members)->pluck('name')->all() === ['Anggota Satu']));
});

it('needs a signed in user', function () {
    $this->get(route('organization.index'))->assertRedirect(route('login'));
});
