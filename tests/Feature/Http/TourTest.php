<?php

use App\Models\TourCompletion;
use App\Models\User;

beforeEach(fn () => seedRolesAndPermissions());

it('remembers a finished tour once and shares it with the page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tour.complete', 'skp.index'))->assertNoContent();
    $this->actingAs($user)->post(route('tour.complete', 'skp.index'))->assertNoContent();

    expect(TourCompletion::where('user_id', $user->id)->count())->toBe(1);
    $this->actingAs($user)->get(route('profile.edit'))->assertInertia(fn ($p) => $p->where('tours_done', ['skp.index']));
});

it('keeps a tour per user and lets a user start over', function () {
    [$a, $b] = [User::factory()->create(), User::factory()->create()];
    TourCompletion::create(['user_id' => $a->id, 'tour_key' => 'weekly.index']);
    TourCompletion::create(['user_id' => $b->id, 'tour_key' => 'weekly.index']);

    $this->actingAs($a)->delete(route('tour.reset'))->assertNoContent();

    expect(TourCompletion::where('user_id', $a->id)->count())->toBe(0)->and(TourCompletion::where('user_id', $b->id)->count())->toBe(1);
});

it('refuses a tour key that is not a plain name', function () {
    $this->actingAs(User::factory()->create())->post(route('tour.complete', 'a b'))->assertNotFound();
});
