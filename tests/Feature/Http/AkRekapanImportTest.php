<?php

use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\User;

function akRow(array $over = []): array
{
    return ['nip' => '196702081992121001', 'name' => 'Ir. Jefrie Wahido', 'integrasi' => 204.62, 'y2023' => 56.25, 'y2024' => 56.25, 'y2025' => 37.5, 'jumlah' => 354.62, 'note' => null, ...$over];
}

it('matches by NIP and proposes the Jumlah, or integrasi plus the three years without one', function () {
    $e = Employee::factory()->create(['nip_baru' => '196702081992121001']);
    EmployeeCareer::create(['employee_id' => $e->id, 'jabatan' => 'Statistisi Ahli Madya', 'golongan' => 'IV/c']);

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-ak.preview'), ['rows' => [
        akRow(),
        akRow(['nip' => '199001012020012001', 'jumlah' => null]),
        akRow(['jumlah' => null, 'integrasi' => null, 'y2023' => null, 'y2024' => null, 'y2025' => 12.5]),
    ]])->assertOk()->json('rows');

    expect($rows[0])->employee_id->toBe($e->id)->proposed->toEqual(354.62)->include->toBeTrue()->blocked->toBeFalse()
        ->and($rows[1]['blocked'])->toBeTrue()->and($rows[1]['notes'])->toContain('NIP tidak ada di Kinetik.')
        ->and($rows[2]['proposed'])->toEqual(12.5)->and($rows[2]['notes'])->toContain('Tanpa integrasi: angka dihitung dari AK 2023 sampai 2025 saja.');
});

it('does not tick a row whose note states the starting AK somewhere else', function () {
    Employee::factory()->create(['nip_baru' => '196702081992121001']);

    $row = $this->actingAs(adminUser())->postJson(route('admin.import-ak.preview'), ['rows' => [akRow(['jumlah' => null, 'integrasi' => null, 'note' => 'PJL awal = 121.875; Muda'])]])->json('rows.0');

    expect($row['include'])->toBeFalse()->and($row['blocked'])->toBeFalse();
});

it('saves the cumulative AK as the base at the chosen date, for admins only', function () {
    $e = Employee::factory()->create();
    $payload = ['base_date' => '2025-12-31', 'rows' => [['employee_id' => $e->id, 'ak' => 354.62]]];

    $this->actingAs(User::factory()->create())->postJson(route('admin.import-ak.store'), $payload)->assertForbidden();
    $this->actingAs(adminUser())->postJson(route('admin.import-ak.store'), $payload)->assertOk()->assertJsonPath('saved', 1);

    expect($e->career->fresh())->ak_base->toEqual(354.62)->ak_base_source->toBe('rekapan')
        ->and($e->career->fresh()->ak_base_date->toDateString())->toBe('2025-12-31');
});
