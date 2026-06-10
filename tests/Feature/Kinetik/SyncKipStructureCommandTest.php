<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api',
        'kinetik.kip.token' => 'test-token-abc',
        'kinetik.kip.timeout' => 15,
        'kinetik.kip.tahun' => 2026,
    ]);
});

it('syncs explicit team ids and reports the summary', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([
            [
                'timkerjaid' => '106436', 'namatim' => 'UMUM',
                'niplamaketua' => '340013832', 'namaketua' => 'Imron',
                'proyekid' => '427670', 'namaproyek' => 'Pengembangan SDM',
                'anggota' => [['anggotaid' => 'a1', 'niplama' => '340013832', 'nama' => 'Imron']],
            ],
        ], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([
            ['anggotaid' => 'm1', 'niplama' => '340013832', 'nama' => 'Imron'],
        ], 200),
    ]);
    Employee::factory()->create(['nip_lama' => '340013832']);

    $this->artisan('kinetik:sync-kip-structure', ['--timkerja' => ['106436']])
        ->expectsOutputToContain('Teams: 1')
        ->assertSuccessful();

    expect(Team::where('kip_external_id', '106436')->exists())->toBeTrue()
        ->and(Project::where('kip_external_id', '427670')->exists())->toBeTrue();
});

it('fails when no token is configured and no team ids are given', function () {
    config(['kinetik.kip.token' => null]);

    $this->artisan('kinetik:sync-kip-structure')
        ->assertFailed();
});
