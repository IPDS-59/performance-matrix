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

it('filters activities by week, month or quarter, counting multi-week activities', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $make = fn (string $from, string $to) => KipActivity::factory()->create(['employee_id' => $employee->id, 'activity_date_start' => $from, 'activity_date_end' => $to]);
    $quarterly = $make('2026-07-01', '2026-09-30');
    $august = $make('2026-08-12', '2026-08-12');
    $june = $make('2026-06-15', '2026-06-15');

    $ids = fn (array $query) => collect($this->actingAs($user)->get(route('kip-activities.index', $query))->viewData('page')['props']['activities']['data'])->pluck('id')->sort()->values()->all();

    expect($ids(['period' => 'week', 'date' => '2026-08-12']))->toBe(collect([$quarterly->id, $august->id])->sort()->values()->all())
        ->and($ids(['period' => 'month', 'date' => '2026-06-01']))->toBe([$june->id])
        ->and($ids(['period' => 'quarter', 'date' => '2026-08-01']))->toBe(collect([$quarterly->id, $august->id])->sort()->values()->all())
        ->and(count($ids([])))->toBe(3);
});
