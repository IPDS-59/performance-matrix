<?php

use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipTeamData;

it('maps a team node from the lokasi tree', function () {
    $team = KipTeamData::fromApiRow([
        'id' => '106453',
        'timkerja' => 'METODOLOGI DAN TEKNOLOGI INFORMASI (MTI)',
        'isketuatim' => 0,
        'proyek' => [['id' => '489023', 'proyek' => 'Inovasi']],
    ]);

    expect($team->externalId)->toBe('106453')
        ->and($team->name)->toBe('METODOLOGI DAN TEKNOLOGI INFORMASI (MTI)');
});

it('maps a member row with niplama and jabatan', function () {
    $member = KipMemberData::fromApiRow([
        'anggotaid' => '3342414',
        'pegawaiid' => '2888',
        'niplama' => '340056751',
        'nipbaru' => '199106122014101001',
        'nama' => 'Bayu Setyawan SST, M.T.',
        'jabatanid' => '50',
        'namajabatan' => 'Statistisi Ahli Pertama',
    ]);

    expect($member->anggotaId)->toBe('3342414')
        ->and($member->nipLama)->toBe('340056751')
        ->and($member->nipBaru)->toBe('199106122014101001')
        ->and($member->name)->toBe('Bayu Setyawan SST, M.T.')
        ->and($member->jabatanName)->toBe('Statistisi Ahli Pertama');
});

it('maps a team member row that uses anggotatimid + namaanggota keys', function () {
    $member = KipMemberData::fromApiRow([
        'anggotatimid' => '562376',
        'pegawaiid' => '2888',
        'niplama' => '589999999',
        'namaanggota' => 'Salahudin SE',
    ]);

    expect($member->anggotaId)->toBe('562376')
        ->and($member->name)->toBe('Salahudin SE');
});

it('maps a project row with inline members and trims the project name', function () {
    $project = KipProjectData::fromApiRow([
        'timkerjaid' => '106436',
        'namatim' => 'UMUM',
        'niplamaketua' => '340013832',
        'namaketua' => 'Imron Taufik J Musa S.Si, M.Si',
        'proyekid' => '427670',
        'namaproyek' => ' Pengembangan Kompetensi SDM yang Optimal',
        'anggota' => [
            ['anggotaid' => '3342414', 'pegawaiid' => '2888', 'niplama' => '589999999', 'nama' => 'Salahudin SE'],
            ['anggotaid' => '3342415', 'pegawaiid' => '47912', 'niplama' => '340053881', 'nama' => 'Asmawati S.Si'],
        ],
    ]);

    expect($project->externalId)->toBe('427670')
        ->and($project->name)->toBe('Pengembangan Kompetensi SDM yang Optimal')
        ->and($project->teamExternalId)->toBe('106436')
        ->and($project->teamName)->toBe('UMUM')
        ->and($project->leaderNipLama)->toBe('340013832')
        ->and($project->members)->toHaveCount(2)
        ->and($project->members->first()->nipLama)->toBe('589999999');
});

it('tolerates a project row with no members', function () {
    $project = KipProjectData::fromApiRow([
        'proyekid' => '1',
        'namaproyek' => 'Tanpa Anggota',
        'timkerjaid' => '9',
    ]);

    expect($project->members)->toHaveCount(0)
        ->and($project->leaderNipLama)->toBeNull();
});
