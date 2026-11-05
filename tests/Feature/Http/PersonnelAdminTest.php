<?php

use App\Models\Employee;
use App\Models\User;

function personnelUser(): User
{
    seedRolesAndPermissions();
    $user = User::factory()->create();
    $user->assignRole('staff');
    $user->assignRole('admin-kepegawaian');

    return $user;
}

it('lets the personnel admin use Impor AK, the employee list and AK editing', function () {
    $user = personnelUser();
    $employee = Employee::factory()->create();

    $this->actingAs($user)->get(route('admin.import-ak'))->assertOk();
    $this->actingAs($user)->get(route('employees.index'))->assertOk();
    $this->actingAs($user)->get(route('credit.team'))->assertOk();
    $this->actingAs($user)->put(route('credit.base', $employee), ['ak_base' => 10, 'ak_base_date' => '2025-12-31'])->assertSessionHasNoErrors();
});

it('keeps the integration tools for the full admin', function () {
    $user = personnelUser();

    $this->actingAs($user)->get(route('kip-integration.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.features'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.impersonate'))->assertForbidden();
});

it('keeps Impor AK away from an ordinary member', function () {
    seedRolesAndPermissions();
    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($user)->get(route('admin.import-ak'))->assertForbidden();
});

it('lets only the full admin give or take back the role', function () {
    $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs(personnelUser())->put(route('employees.personnel-admin', $employee))->assertForbidden();

    $this->actingAs(adminUser())->put(route('employees.personnel-admin', $employee))->assertRedirect();
    expect($employee->user->fresh()->hasRole('admin-kepegawaian'))->toBeTrue();

    $this->actingAs(adminUser())->put(route('employees.personnel-admin', $employee))->assertRedirect();
    expect($employee->user->fresh()->hasRole('admin-kepegawaian'))->toBeFalse();
});
