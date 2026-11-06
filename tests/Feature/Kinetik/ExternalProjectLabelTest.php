<?php

use App\Actions\Kinetik\BuildMemberWeekAction;
use App\Actions\Kinetik\LabelExternalProjectsAction;
use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipRkData;
use App\Kinetik\Sources\MockKipStructureSource;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Team;
use Illuminate\Support\Collection;

beforeEach(fn () => config(['kinetik.kip.tahun' => 2026]));

/** kipApp as seen by the unit admin token: one Pusat team with the projek below. */
function pusatSource(array $projects, array $rkRows = []): MockKipStructureSource
{
    return new class($projects, $rkRows) extends MockKipStructureSource
    {
        public int $projectCalls = 0;

        public function __construct(private array $projects, private array $rkRows) {}

        public function fetchTeamProjects(string $timkerjaId): Collection
        {
            $this->projectCalls++;

            return collect($this->projects)->map(fn (array $p) => KipProjectData::fromApiRow($p + ['timkerjaid' => $timkerjaId]));
        }

        public function fetchProjectRkRows(string $timkerjaId, string $proyekId, ?array $scope = null): array
        {
            return $this->rkRows[$proyekId] ?? [];
        }
    };
}

function pusatRk(string $id, string $text, string $leaderRk): KipRkData
{
    return KipRkData::fromApiRow(['rkid' => $id, 'rencanakinerja' => $text, 'rencanakinerjaatasan' => $leaderRk, 'timkerjaid' => '62386', 'namatim' => 'Asesmen dan Konseling', 'jeniskinerja' => 1, 'tahun' => 2026]);
}

function rika(): Employee
{
    return Employee::factory()->create(['nip_lama' => '340099001']);
}

function ownedPlan(Employee $employee, string $text): PerformancePlan
{
    return PerformancePlan::factory()->create(['team_id' => null, 'project_id' => null, 'description' => $text, 'is_additional' => false, 'owner_employee_id' => $employee->id]);
}

it('labels a Pusat RK with its team and the projek the member is in under the same RK Ketua', function () {
    $employee = rika();
    $plan = ownedPlan($employee, 'Terlaksananya penilaian potensi pegawai');
    $source = pusatSource([
        ['proyekid' => '1', 'namaproyek' => 'Kegiatan Penilaian Potensi dan Kompetensi sesuai kebutuhan organisasi', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340099001', 'nama' => 'Rika']]],
        ['proyekid' => '2', 'namaproyek' => 'Konseling Pegawai', 'rencanakinerjaketua' => 'Terselenggaranya layanan konseling', 'anggota' => [['niplama' => '340099001', 'nama' => 'Rika']]],
        ['proyekid' => '3', 'namaproyek' => 'Asesmen Pejabat', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340011111', 'nama' => 'Orang lain']]],
    ]);

    (new LabelExternalProjectsAction($source))->execute($employee, collect([pusatRk('7001', 'Terlaksananya penilaian potensi pegawai', 'Terselenggaranya  asesmen pegawai')]));

    expect($plan->fresh())
        ->external_team_name->toBe('Asesmen dan Konseling')
        ->external_project_name->toBe('Kegiatan Penilaian Potensi dan Kompetensi sesuai kebutuhan organisasi')
        ->project_id->toBeNull();
});

it('asks kipApp which projek holds the RK when the member is in several under the same RK Ketua', function () {
    $employee = rika();
    $plan = ownedPlan($employee, 'Terlaksananya penilaian potensi pegawai');
    $source = pusatSource([
        ['proyekid' => '1', 'namaproyek' => 'Penilaian Potensi', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340099001']]],
        ['proyekid' => '2', 'namaproyek' => 'Asesmen Pejabat', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340099001']]],
    ], [
        '1' => [['text' => 'Terlaksananya penilaian potensi pegawai', 'niplama' => '340099001']],
        '2' => [['text' => 'RK lain', 'niplama' => '340099001']],
    ]);

    (new LabelExternalProjectsAction($source))->execute($employee, collect([pusatRk('7001', 'Terlaksananya penilaian potensi pegawai', 'Terselenggaranya asesmen pegawai')]));

    expect($plan->fresh()->external_project_name)->toBe('Penilaian Potensi');
});

it('keeps only the team name when the projek cannot be told apart', function () {
    $employee = rika();
    $plan = ownedPlan($employee, 'Terlaksananya pekerjaan rutin bagian umum');
    $source = pusatSource([
        ['proyekid' => '1', 'namaproyek' => 'Penilaian Potensi', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340099001']]],
        ['proyekid' => '2', 'namaproyek' => 'Asesmen Pejabat', 'rencanakinerjaketua' => 'Terselenggaranya asesmen pegawai', 'anggota' => [['niplama' => '340099001']]],
    ]);

    (new LabelExternalProjectsAction($source))->execute($employee, collect([pusatRk('7001', 'Terlaksananya pekerjaan rutin bagian umum', 'Terselenggaranya asesmen pegawai')]));

    expect($plan->fresh())->external_team_name->toBe('Asesmen dan Konseling')->external_project_name->toBeNull();
});

it('leaves RK of Sulteng teams alone and reads each Pusat team once', function () {
    $employee = rika();
    Team::factory()->create(['kip_external_id' => '106453']);
    $sulteng = ownedPlan($employee, 'RK tim Sulteng');
    ownedPlan($employee, 'RK pusat satu');
    ownedPlan($employee, 'RK pusat dua');
    $source = pusatSource([['proyekid' => '1', 'namaproyek' => 'Penilaian Potensi', 'rencanakinerjaketua' => 'Ketua', 'anggota' => [['niplama' => '340099001']]]]);

    (new LabelExternalProjectsAction($source))->execute($employee, collect([
        KipRkData::fromApiRow(['rkid' => '1', 'rencanakinerja' => 'RK tim Sulteng', 'timkerjaid' => '106453', 'namatim' => 'MTI', 'tahun' => 2026]),
        pusatRk('2', 'RK pusat satu', 'Ketua'),
        pusatRk('3', 'RK pusat dua', 'Ketua'),
    ]));

    expect($sulteng->fresh()->external_team_name)->toBeNull()
        ->and($source->projectCalls)->toBe(1)
        ->and(PerformancePlan::where('external_project_name', 'Penilaian Potensi')->count())->toBe(2);
});

it('shows the Pusat team and projek on the RK in the kegiatan form', function () {
    $employee = rika();
    $plan = ownedPlan($employee, 'RK pusat');
    $plan->forceFill(['external_team_name' => 'Asesmen dan Konseling', 'external_project_name' => 'Penilaian Potensi'])->save();
    $bare = ownedPlan($employee, 'RK pusat tanpa label');

    $week = app(BuildMemberWeekAction::class);
    $options = collect((new ReflectionMethod($week, 'rkOptions'))->invoke($week, $employee, 2026));

    expect($options->firstWhere('id', $plan->id))->toMatchArray(['team_name' => 'Asesmen dan Konseling', 'external_project' => 'Penilaian Potensi'])
        ->and($options->firstWhere('id', $bare->id))->toMatchArray(['team_name' => 'Di luar tim Sulteng', 'external_project' => null]);
});

it('resolves the action with the bound kipApp source', function () {
    app()->instance(KipStructureSource::class, pusatSource([]));

    expect(app(LabelExternalProjectsAction::class))->toBeInstanceOf(LabelExternalProjectsAction::class);
});

it('tells two projek under one RK Ketua apart by the words of a reworded RK, reading rk/proyek with the projek\'s own wilayah', function () {
    $employee = rika();
    $assessment = ownedPlan($employee, 'Terselenggaranya kegiatan Penilaian Potensi dan Kompetensi (Asesmen) pegawai sesuai kebutuhan organisasi');
    $tools = ownedPlan($employee, 'Terselenggaranya pengembangan alat ukur dan aplikasi Assessment Center BPS');
    $leader = 'Pengelolaan SDM melalui asesmen (penilaian potensi dan kompetensi) Pegawai sesuai kebutuhan organisasi';
    $scope = ['wilayahid' => '0000_11', 'unitkerjaid' => '58'];
    $member = [['niplama' => '340099001', 'nama' => 'Rika']];

    $source = new class([['proyekid' => '1', 'namaproyek' => 'Kegiatan Penilaian Potensi dan Kompetensi sesuai kebutuhan organisasi', 'rencanakinerjaketua' => $leader, 'anggota' => $member] + $scope, ['proyekid' => '2', 'namaproyek' => 'Pengembangan Alat Ukur dan Aplikasi Assessment Center BPS', 'rencanakinerjaketua' => $leader, 'anggota' => $member] + $scope]) extends MockKipStructureSource
    {
        public array $scopes = [];

        public function __construct(private array $projects) {}

        public function fetchTeamProjects(string $timkerjaId): Collection
        {
            return collect($this->projects)->map(fn (array $p) => KipProjectData::fromApiRow($p + ['timkerjaid' => $timkerjaId]));
        }

        public function fetchProjectRkRows(string $timkerjaId, string $proyekId, ?array $scope = null): array
        {
            $this->scopes[] = $scope;
            if (($scope['wilayahid'] ?? null) !== '0000_11') {
                return [];
            }

            return [['niplama' => '340099001', 'text' => $proyekId === '1' ? 'Laporan Asesmen Kompetensi ASN sesuai kebutuhan organisasi' : 'Dokumen Pengembangan Alat Ukur dan Aplikasi Assessment Center BPS yang sesuai NSPK']];
        }
    };

    (new LabelExternalProjectsAction($source))->execute($employee, collect([
        pusatRk('11', $assessment->description, $leader),
        pusatRk('12', $tools->description, $leader),
    ]));

    expect($assessment->fresh()->external_project_name)->toBe('Kegiatan Penilaian Potensi dan Kompetensi sesuai kebutuhan organisasi')
        ->and($tools->fresh()->external_project_name)->toBe('Pengembangan Alat Ukur dan Aplikasi Assessment Center BPS')
        ->and($source->scopes[0])->toBe(['wilayahid' => '0000_11', 'unitkerjaid' => '58']);
});
