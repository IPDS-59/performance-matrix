<?php

use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use App\Notifications\KinetikNotification;

function teamWithPjAndMember(): array
{
    $pjUser = staffUser();
    $pj = Employee::factory()->create(['user_id' => $pjUser->id, 'display_name' => 'Sukma']);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $memberUser = User::factory()->create();
    $member = Employee::factory()->create(['user_id' => $memberUser->id]);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    return [$pjUser, $team, $member, $memberUser];
}

it('notifies the member when the PJ sets their weekly focus, once per change', function () {
    [$pjUser, $team, $member, $memberUser] = teamWithPjAndMember();
    $payload = ['team_id' => $team->id, 'employee_id' => $member->id, 'week_start' => '2026-10-05', 'body' => 'Entri SE2026'];

    $this->actingAs($pjUser)->post(route('weekly-plan.focus'), $payload);
    $this->actingAs($pjUser)->post(route('weekly-plan.focus'), $payload); // unchanged: no second notification

    expect($memberUser->notifications)->toHaveCount(1)
        ->and($memberUser->notifications->first()->data)->toMatchArray(['type' => 'weekly_focus'])
        ->and($memberUser->notifications->first()->data['message'])->toContain('Sukma mengisi fokus Anda')->toContain('Entri SE2026')
        ->and($memberUser->notifications->first()->data['url'])->toContain('/rekap-mingguan');
});

it('notifies the team PJ when the head writes a note', function () {
    [$pjUser, $team] = teamWithPjAndMember();
    $period = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs(headUser())->post(route('team-recap.leadership-note'), [...$period, 'body' => 'Percepat pencacahan'])->assertRedirect();

    expect($pjUser->notifications)->toHaveCount(1)
        ->and($pjUser->notifications->first()->data['message'])->toContain('Catatan pimpinan untuk '.$team->name)->toContain('Percepat pencacahan')
        ->and($pjUser->notifications->first()->data['url'])->toContain('rekap-semua-tim')->toContain('month=6');
});

it('lists, reads and deletes only the user own notifications', function () {
    $user = staffUser();
    $other = User::factory()->create();
    $user->notify(new KinetikNotification('weekly_focus', 'Halo', '/rencana-minggu'));
    $other->notify(new KinetikNotification('weekly_focus', 'Rahasia'));
    $mine = $user->notifications()->first();
    $theirs = $other->notifications()->first();

    $this->actingAs($user)->getJson(route('notifications.index'))
        ->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('notifications.0.message', 'Halo')->assertJsonCount(1, 'notifications');

    $this->actingAs($user)->patchJson(route('notifications.read', $theirs->id))->assertOk();
    $this->actingAs($user)->deleteJson(route('notifications.destroy', $theirs->id))->assertOk();
    expect($theirs->fresh())->not->toBeNull()->read_at->toBeNull();

    $this->actingAs($user)->patchJson(route('notifications.read', $mine->id))->assertOk();
    expect($mine->fresh()->read_at)->not->toBeNull();

    $user->notify(new KinetikNotification('weekly_focus', 'Dua'));
    $this->actingAs($user)->patchJson(route('notifications.read-all'))->assertOk();
    expect($user->unreadNotifications()->count())->toBe(0);

    $this->actingAs($user)->deleteJson(route('notifications.destroy', $mine->id))->assertOk();
    expect($mine->fresh())->toBeNull();
    $this->actingAs($user)->get(route('notifications.page'))->assertOk();
});
