<?php

use App\Models\Employee;
use App\Models\ImpersonationAudit;
use App\Models\Team;
use App\Models\TourCompletion;
use App\Models\User;

function memberUser(): User
{
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    return $user;
}

it('lets only an admin open the page and lists members, PJ and head but never admins', function () {
    $admin = adminUser();
    $member = memberUser();
    $pj = memberUser();
    Team::factory()->create(['leader_id' => $pj->employee->id]);
    $head = headUser();
    $head->update(['role' => 'head']);
    $gone = memberUser();
    $gone->employee->update(['is_active' => false]);

    $this->actingAs($member)->get(route('admin.impersonate'))->assertForbidden();

    $this->actingAs($admin)->get(route('admin.impersonate'))->assertInertia(fn ($page) => $page
        ->where('users', fn ($users) => collect($users)->firstWhere('id', $pj->id)['level'] === 'pj'
            && collect($users)->firstWhere('id', $member->id)['level'] === 'anggota'
            && collect($users)->firstWhere('id', $head->id)['level'] === 'pimpinan'
            && collect($users)->doesntContain('id', $admin->id)
            && collect($users)->doesntContain('id', $gone->id)));
});

it('signs the admin in as the chosen user, shows the banner data and signs back out of the view', function () {
    $admin = adminUser();
    $member = memberUser();

    $this->actingAs($admin)->post(route('admin.impersonate.start', $member))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($member);
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('impersonating.name', fn ($n) => filled($n)));

    $this->post(route('admin.impersonate.stop'))->assertRedirect(route('admin.impersonate'));
    $this->assertAuthenticatedAs($admin);
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('impersonating', null));
});

it('refuses to impersonate an admin, to impersonate twice, or for a non admin', function () {
    $admin = adminUser();
    $other = adminUser();
    $member = memberUser();

    $this->actingAs($admin)->post(route('admin.impersonate.start', $other))->assertForbidden();
    $this->actingAs($member)->post(route('admin.impersonate.start', $other))->assertForbidden();

    $this->actingAs($admin)->post(route('admin.impersonate.start', $member))->assertRedirect();
    // While acting as a member the session user is the member, so a second start is refused.
    $this->post(route('admin.impersonate.start', memberUser()))->assertForbidden();
    $this->assertAuthenticatedAs($member);
    $this->post(route('admin.impersonate.stop'))->assertRedirect();
    $this->post(route('admin.impersonate.stop'))->assertNotFound();
});

it('lets an admin save as the person, audits it, and still blocks account takeover', function () {
    $admin = adminUser();
    $member = memberUser();
    $this->actingAs($admin)->post(route('admin.impersonate.start', $member));

    $this->from(route('dashboard'))->post(route('tour.complete', 'skp.index'))->assertNoContent();
    expect(TourCompletion::where('user_id', $member->id)->exists())->toBeTrue();
    $audit = ImpersonationAudit::sole();
    expect($audit)->admin_id->toBe($admin->id)->user_id->toBe($member->id)->method->toBe('POST')->route->toBe('tour.complete');

    $this->from(route('dashboard'))->patch(route('profile.update'), ['name' => 'Diubah'])->assertSessionHas('error');
    expect($member->fresh()->name)->not->toBe('Diubah')->and(ImpersonationAudit::count())->toBe(1);

    $this->post(route('admin.impersonate.stop'))->assertRedirect(route('admin.impersonate'));
    $this->assertAuthenticatedAs($admin);
});

it('does not send the admin to the connect page while viewing as someone who has not connected', function () {
    $admin = adminUser();
    $member = memberUser();
    $member->update(['email' => 'someone@bps.go.id']);
    config(['kinetik.kip.credential_key' => 'test-key', 'kinetik.kip.sso_login' => true]);

    $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('kip.connect'));

    $this->actingAs($admin)->post(route('admin.impersonate.start', $member));

    $this->get(route('dashboard'))->assertOk();
});
