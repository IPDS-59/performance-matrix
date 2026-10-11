<?php

use App\Models\Employee;
use App\Models\Team;

it('opens the guide on the viewer\'s own role', function (string $who, string $tab) {
    $user = match ($who) {
        'admin' => adminUser(),
        'head' => headUser(),
        default => staffUser(),
    };
    if ($who === 'pj') {
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        Team::factory()->create(['leader_id' => $employee->id]);
    }

    $this->actingAs($user)
        ->get(route('guide'))
        ->assertInertia(fn ($page) => $page->component('Guide/Index')->where('defaultRole', $tab));
})->with([
    ['admin', 'admin'],
    ['head', 'pimpinan'],
    ['pj', 'pj'],
    ['staff', 'anggota'],
]);

it('requires login', function () {
    $this->get(route('guide'))->assertRedirect(route('login'));
});
