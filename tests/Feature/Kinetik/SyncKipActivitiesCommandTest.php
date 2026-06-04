<?php

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\KipActivity;

beforeEach(function () {
    // Bind the mock source so the command never touches the network
    app()->bind(KipActivitySource::class, MockKipActivitySource::class);
});

it('runs successfully and upserts rows', function () {
    Employee::factory()->create(['nip_lama' => '340060001']);
    Employee::factory()->create(['nip_lama' => '340060002']);

    $this->artisan('kinetik:sync-kip-activities')
        ->assertSuccessful();

    // MockKipActivitySource returns 3 activities per employee
    expect(KipActivity::count())->toBe(6);
});

it('warns and exits cleanly when no eligible employees exist', function () {
    // No employees with nip_lama
    Employee::factory()->count(3)->create(['nip_lama' => null]);

    $this->artisan('kinetik:sync-kip-activities')
        ->expectsOutputToContain('No active employees')
        ->assertSuccessful();

    expect(KipActivity::count())->toBe(0);
});

it('filters employees when --niplama option is given', function () {
    Employee::factory()->create(['nip_lama' => '111111111']);
    Employee::factory()->create(['nip_lama' => '222222222']);

    $this->artisan('kinetik:sync-kip-activities', ['--niplama' => ['111111111']])
        ->assertSuccessful();

    // Only the first employee synced → 3 rows from mock
    expect(KipActivity::count())->toBe(3);
    expect(KipActivity::where('nip_lama', '111111111')->count())->toBe(3);
    expect(KipActivity::where('nip_lama', '222222222')->count())->toBe(0);
});
