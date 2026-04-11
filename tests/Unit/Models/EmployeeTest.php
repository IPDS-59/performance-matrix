<?php

use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

it('belongs to a team', function () {
    $employee = Employee::factory()->create();

    expect($employee->team)->toBeInstanceOf(Team::class);
});

it('belongs to a user optionally', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $unlinked = Employee::factory()->create(['user_id' => null]);

    expect($employee->user)->toBeInstanceOf(User::class);
    expect($unlinked->user)->toBeNull();
});

it('has many educations', function () {
    $employee = Employee::factory()->create();
    EmployeeEducation::factory()->count(2)->create(['employee_id' => $employee->id]);

    expect($employee->educations)->toHaveCount(2);
});

it('belongs to many projects', function () {
    $employee = Employee::factory()->create();
    $projects = Project::factory()->count(3)->create();
    $employee->projects()->attach($projects->pluck('id'), ['role' => 'member']);

    expect($employee->projects)->toHaveCount(3);
});
