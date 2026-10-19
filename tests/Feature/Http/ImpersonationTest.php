<?php

use App\Models\Employee;
use App\Models\Team;
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
    // A second start while viewing as someone is a write, so the read only guard stops it.
    $this->post(route('admin.impersonate.start', memberUser()))->assertSessionHas('error');
    $this->assertAuthenticatedAs($member);
    $this->post(route('admin.impersonate.stop'))->assertRedirect();
    $this->post(route('admin.impersonate.stop'))->assertNotFound();
});

it('blocks every write while viewing as someone else, but allows leaving the view', function () {
    $admin = adminUser();
    $member = memberUser();
    $this->actingAs($admin)->post(route('admin.impersonate.start', $member));

    $this->from(route('dashboard'))->post(route('plan-items.store'), ['description' => 'x'])->assertRedirect(route('dashboard'))->assertSessionHas('error');
    $this->patch(route('profile.update'), ['name' => 'Diubah'])->assertSessionHas('error');
    expect($member->fresh()->name)->not->toBe('Diubah');

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
