<?php

use App\Kinetik\FeatureFlags;
use App\Models\FeatureFlag;
use App\Models\User;

beforeEach(fn () => seedRolesAndPermissions());

it('is for admins only', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.features'))->assertForbidden();
    $this->actingAs(adminUser())->get(route('admin.features'))->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Features')->has('flags', 5));
});

it('switches a flag on and the config follows at once, then back to the server value', function () {
    config(['kinetik.kip.structure_write' => false]);
    $admin = adminUser();

    $this->actingAs($admin)->put(route('admin.features.update', 'structure_write'), ['enabled' => true])->assertSessionHas('success');
    expect(config('kinetik.kip.structure_write'))->toBeTrue()
        ->and(FeatureFlag::where('key', 'structure_write')->first())->enabled->toBeTrue()->changed_by->toBe($admin->id);

    $this->actingAs($admin)->delete(route('admin.features.reset', 'structure_write'))->assertSessionHas('success');
    expect(FeatureFlag::count())->toBe(0);
});

it('applies a saved choice over the .env value when the app boots', function () {
    config(['kinetik.kip.atasan_rating' => false]);
    FeatureFlag::create(['key' => 'atasan_rating', 'enabled' => true]);

    FeatureFlags::apply();

    expect(config('kinetik.kip.atasan_rating'))->toBeTrue();
});

it('refuses a key that is not a flag', function () {
    $this->actingAs(adminUser())->put(route('admin.features.update', 'sso_login'), ['enabled' => false])->assertNotFound();
});
