<?php

use App\Models\Employee;
use App\Models\KipActivity;

it('scopes the kegiatan list to the staff user\'s own employee', function () {
    $staff = staffUser();
    $employee = Employee::factory()->create(['user_id' => $staff->id]);

    KipActivity::factory()->count(2)->create(['employee_id' => $employee->id]);
    KipActivity::factory()->count(3)->create(); // other employees

    $this->actingAs($staff)
        ->get(route('kip-activities.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/Activities')
            ->where('canViewAll', false)
            ->where('stats.total', 2)
            ->has('activities.data', 2)
        );
});

it('shows an empty list for a user without a linked employee', function () {
    $this->actingAs(staffUser())
        ->get(route('kip-activities.index'))
        ->assertInertia(fn ($page) => $page->has('activities.data', 0));
});

it('lists synced kegiatan for an admin', function () {
    KipActivity::factory()->count(3)->create(['is_claimed' => false]);
    KipActivity::factory()->create(['is_claimed' => true]);

    $this->actingAs(adminUser())
        ->get(route('kip-activities.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/Activities')
            ->where('stats.total', 4)
            ->where('stats.claimed', 1)
            ->has('activities.data', 4)
        );
});

it('filters kegiatan by claimed status', function () {
    KipActivity::factory()->count(2)->create(['is_claimed' => false]);
    KipActivity::factory()->create(['is_claimed' => true]);

    $this->actingAs(adminUser())
        ->get(route('kip-activities.index', ['status' => 'claimed']))
        ->assertInertia(fn ($page) => $page->has('activities.data', 1));
});

it('searches kegiatan by description', function () {
    KipActivity::factory()->create(['description' => 'Monitoring Press Release']);
    KipActivity::factory()->create(['description' => 'Rapat koordinasi']);

    $this->actingAs(adminUser())
        ->get(route('kip-activities.index', ['q' => 'Press']))
        ->assertInertia(fn ($page) => $page->has('activities.data', 1));
});
