<?php

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\FraMonthValue;
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
        $this->actingAs($user)->get(route('fra.index', ['year' => 2026, 'month' => 6]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Kinetik/Fra')->has('indicators', 20)->where('summary.quarter', 2));
    }

    $member = Employee::factory()->create(['user_id' => staffUser()->id]);
    $this->actingAs($member->user)->get(route('fra.index'))->assertForbidden();
});

it('lets only the PJ of the owner team (and admins) write the monthly analysis', function () {
    [$pjUser, $team] = fraPj();
    [$otherPjUser] = fraPj();
    $indicator = FraIndicator::where('code', '2.6.1.1')->first();
    $indicator->update(['owner_team_id' => $team->id]);
    $payload = ['obstacle' => 'Kendala uji', 'pic' => 'Tim IPDS'];

    $this->actingAs($otherPjUser)->patch(route('fra.analysis', [$indicator, 6]), $payload)->assertForbidden();
    $this->actingAs(headUser())->patch(route('fra.analysis', [$indicator, 6]), $payload)->assertForbidden();

    $this->actingAs($pjUser)->patch(route('fra.analysis', [$indicator, 6]), $payload)->assertSessionHas('success');
    expect(FraMonthValue::where(['fra_indicator_id' => $indicator->id, 'month' => 6])->first())
        ->obstacle->toBe('Kendala uji')->updated_by->not->toBeNull();

    $this->actingAs(adminUser())->patch(route('fra.analysis', [$indicator, 13]), $payload)->assertNotFound();

    // The quarterly export cascades the quarter's months, each text with its month.
    $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026, 'month' => 6]))->assertInertia(function ($page) {
        $row = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1');
        expect($row['values'][2]['obstacle'])->toBe('Jun: Kendala uji');
    });
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

    expect($indicator->monthValues()->count())->toBe(4);
    $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026, 'month' => 3]))->assertInertia(function ($page) {
        $row = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1');
        expect($row['months'][2]['x'])->toEqual(8)                        // typed month comes back
            ->and($row['quarters'][1]['realization_value'])->toEqual(60); // (6+8+10)/(10+10+20) = 60%
    });

    $this->actingAs($pjUser)->put(route('fra.months', $indicator), ['months' => [1 => ['x' => null, 'y' => null]]])->assertRedirect();
    $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026, 'month' => 3]))->assertInertia(function ($page) {
        $row = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1');
        expect($row['quarters'][1]['realization_value'])->toEqual(60); // (8+10)/(10+20) once month 1 is cleared
    });
});

it('keeps a system version from the months and an edited version typed on the quarter, edited winning per field', function () {
    [$pjUser, $team] = fraPj();
    [$otherPjUser] = fraPj();
    $indicator = FraIndicator::where('code', '2.6.1.1')->first();
    $indicator->update(['owner_team_id' => $team->id]);
    FraQuarterValue::where('fra_indicator_id', $indicator->id)->update(['obstacle' => null, 'solution' => null, 'follow_up' => null, 'pic' => null, 'deadline' => null, 'evidence_url' => null, 'previous_follow_up_url' => null]);
    $this->actingAs($pjUser)->patch(route('fra.analysis', [$indicator, 4]), ['obstacle' => 'Data terlambat', 'solution' => 'Rapat koordinasi'])->assertSessionHas('success');
    $this->actingAs($pjUser)->patch(route('fra.analysis', [$indicator, 5]), ['obstacle' => 'Data terlambat'])->assertSessionHas('success');

    $this->actingAs($otherPjUser)->patch(route('fra.quarter-analysis', [$indicator, 2]), ['obstacle' => 'x'])->assertForbidden();
    $this->actingAs($pjUser)->patch(route('fra.quarter-analysis', [$indicator, 2]), ['obstacle' => 'Versi PJ', 'solution' => ''])->assertSessionHas('success');

    $read = fn () => $this->actingAs($pjUser)->get(route('fra.index', ['year' => 2026, 'month' => 6]));
    $read()->assertInertia(function ($page) {
        $q2 = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1')['values'][2];
        expect($q2['obstacle'])->toBe('Versi PJ')                                  // edited wins
            ->and($q2['solution'])->toBe('Apr: Rapat koordinasi')                  // empty edit falls back to the system
            ->and($q2['system_analysis']['obstacle'])->toBe('Apr–Mei: Data terlambat')
            ->and($q2['edited_analysis']['obstacle'])->toBe('Versi PJ')
            ->and($q2['edited_analysis']['solution'])->toBeNull();
    });

    $this->actingAs($pjUser)->delete(route('fra.quarter-analysis.reset', [$indicator, 2]))->assertSessionHas('success');
    $read()->assertInertia(function ($page) {
        $q2 = collect($page->toArray()['props']['indicators'])->firstWhere('code', '2.6.1.1')['values'][2];
        expect($q2['obstacle'])->toBe('Apr–Mei: Data terlambat')->and($q2['edited_analysis']['obstacle'])->toBeNull();
    });
});
