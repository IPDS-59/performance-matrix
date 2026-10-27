<?php

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\FraQuarterValue;
use App\Models\Team;
use Database\Seeders\FraIndicatorSeeder;

function fraPj(): array
{
    $user = staffUser();
    $pj = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $pj->id]);

    return [$user, $team];
}

beforeEach(fn () => $this->seed(FraIndicatorSeeder::class));

it('shows the indicators to the head, admins and PJ, but not to members', function () {
    [$pjUser] = fraPj();

    foreach ([$pjUser, headUser(), adminUser()] as $user) {
        $this->actingAs($user)->get(route('fra.index', ['year' => 2026, 'quarter' => 2]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Kinetik/Fra')->has('indicators', 20)->where('summary.quarter', 2));
    }

    $member = Employee::factory()->create(['user_id' => staffUser()->id]);
    $this->actingAs($member->user)->get(route('fra.index'))->assertForbidden();
});

it('lets only the PJ of the owner team (and admins) enter figures', function () {
    [$pjUser, $team] = fraPj();
    [$otherPjUser] = fraPj();
    $indicator = FraIndicator::where('code', '2.6.1.1')->first();
    $indicator->update(['owner_team_id' => $team->id]);
    $payload = ['realization_x' => 25, 'realization_y' => 40, 'obstacle' => 'Kendala uji'];

    $this->actingAs($otherPjUser)->patch(route('fra.values', [$indicator, 2]), $payload)->assertForbidden();
    $this->actingAs(headUser())->patch(route('fra.values', [$indicator, 2]), $payload)->assertForbidden();

    $this->actingAs($pjUser)->patch(route('fra.values', [$indicator, 2]), $payload)->assertSessionHas('success');
    expect(FraQuarterValue::where(['fra_indicator_id' => $indicator->id, 'quarter' => 2])->first())
        ->realization_x->toBe(25.0)->obstacle->toBe('Kendala uji')->updated_by->not->toBeNull();

    $this->actingAs(adminUser())->patch(route('fra.values', [$indicator, 3]), ['realization_x' => 1])->assertSessionHas('success');
});

it('marks only the owned indicators editable for a PJ', function () {
    [$pjUser, $team] = fraPj();
    FraIndicator::where('code', '2.6.1.1')->update(['owner_team_id' => $team->id]);
    $id = FraIndicator::where('code', '2.6.1.1')->value('id');

    $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026]))
        ->assertInertia(fn ($page) => $page->where('editableIds', [$id])->where('isAdmin', false));
});

it('lets only admins assign the owner team', function () {
    [$pjUser, $team] = fraPj();
    $indicator = FraIndicator::first();

    $this->actingAs($pjUser)->patch(route('fra.owner', $indicator), ['owner_team_id' => $team->id])->assertForbidden();
    $this->actingAs(adminUser())->patch(route('fra.owner', $indicator), ['owner_team_id' => $team->id])->assertSessionHas('success');
    expect($indicator->fresh()->owner_team_id)->toBe($team->id);

    $this->actingAs(adminUser())->patch(route('fra.owner', $indicator), ['owner_team_id' => null]);
    expect($indicator->fresh()->owner_team_id)->toBeNull();
});

it('saves the monthly X and Y of the owner team and builds the quarter from them', function () {
    [$pjUser, $team] = fraPj();
    [$otherPjUser] = fraPj();
    $indicator = FraIndicator::where('code', '2.6.1.1')->first();
    $indicator->update(['owner_team_id' => $team->id]);
    $payload = ['months' => [
        1 => ['x' => 6, 'y' => 10], 2 => ['x' => 8, 'y' => 10], 3 => ['x' => 10, 'y' => 20], 4 => ['x' => null, 'y' => null],
    ]];

    $this->actingAs($otherPjUser)->put(route('fra.months', $indicator), $payload)->assertForbidden();
    $this->actingAs($pjUser)->put(route('fra.months', $indicator), $payload)->assertRedirect();

    expect($indicator->monthValues()->count())->toBe(3);
    $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026, 'quarter' => 1]))->assertInertia(function ($page) {
        $row = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1');
        expect($row['months'][2]['x'])->toEqual(8)                        // typed month comes back
            ->and($row['quarters'][1]['realization_value'])->toEqual(60); // (6+8+10)/(10+10+20) = 60%
    });

    $this->actingAs($pjUser)->put(route('fra.months', $indicator), ['months' => [1 => ['x' => null, 'y' => null]]])->assertRedirect();
    expect($indicator->monthValues()->count())->toBe(2);
});
