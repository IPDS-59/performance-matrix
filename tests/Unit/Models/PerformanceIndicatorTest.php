<?php

use App\Models\PerformanceIndicator;
use App\Models\Project;
use App\Models\Team;

it('belongs to a team', function () {
    $indicator = PerformanceIndicator::factory()->create();

    expect($indicator->team)->toBeInstanceOf(Team::class);
});

it('has many projects', function () {
    $indicator = PerformanceIndicator::factory()->create();
    Project::factory()->count(2)->create(['performance_indicator_id' => $indicator->id]);

    expect($indicator->projects)->toHaveCount(2);
});

it('casts year to integer', function () {
    $indicator = PerformanceIndicator::factory()->create(['year' => 2026]);

    expect($indicator->year)->toBe(2026);
});

it('casts target to decimal string', function () {
    $indicator = PerformanceIndicator::factory()->create(['target' => 10.50]);

    expect((float) $indicator->target)->toBe(10.50);
});
