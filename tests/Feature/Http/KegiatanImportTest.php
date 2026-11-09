<?php

use App\Actions\Kinetik\PushDuePlansAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\KegiatanDuplicateFinder;

function importSetup(): array
{
    $team = Team::factory()->create(['name' => 'Tim Neraca']);
    $project = Project::factory()->create(['team_id' => $team->id, 'name' => 'Publikasi PDRB Lapangan Usaha', 'year' => (int) config('kinetik.kip.tahun')]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id, 'description' => 'Menyusun publikasi PDRB', 'target_unit' => 'dokumen']);
    $member = Employee::factory()->create(['name' => 'Aditya Saputra, SST, M.URP', 'is_active' => true]);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    return [$team, $project, $plan, $member];
}

function sheetRow(array $over = []): array
{
    return ['sheet' => 'Neraca Wilayah', 'member' => 'Aditya Saputra, SST', 'project' => 'Publikasi PDRB', 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'target' => 4, 'realization' => 3, 'percent' => 75, ...$over];
}

it('matches the member by name without titles, the projek loosely, and picks the only RK', function () {
    [, $project, $plan, $member] = importSetup();

    $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-28', 'rows' => [sheetRow()]])
        ->assertOk()
        ->assertJsonPath('rows.0.employee_id', $member->id)
        ->assertJsonPath('rows.0.project_id', $project->id)
        ->assertJsonPath('rows.0.performance_plan_id', $plan->id)
        ->assertJsonPath('rows.0.date_start', '2026-10-26')     // no date: the week's Monday ...
        ->assertJsonPath('rows.0.date_end', '2026-10-30')       // ... to Friday
        ->assertJsonPath('rows.0.progress', 75)
        ->assertJsonPath('rows.0.blocked', false);
});

it('flags unknown members, short uraian and rows that already exist', function () {
    [, , $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30']);

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['member' => 'Orang Tidak Dikenal']),
        sheetRow(['description' => 'Pendek']),
        sheetRow(),
    ]])->json('rows');

    expect($rows[0]['blocked'])->toBeTrue()->and($rows[1]['blocked'])->toBeTrue()
        ->and($rows[2]['duplicate'])->toBeTrue();
});

it('saves the confirmed rows as planned kegiatan and skips duplicates', function () {
    [, $project, $plan, $member] = importSetup();
    $admin = adminUser();   // no employee record: the import must still work
    $row = ['employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30', 'target' => 4, 'capaian' => 3, 'progress' => 75];

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [$row]])->assertOk()->assertJsonPath('created', 1);
    expect(PlanItem::first())->source->toBe('import')->created_by->toBeNull()->status->toBe('planned')->progress->toBe(75)->target_unit->toBe('dokumen');

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [$row]])->assertOk()->assertJsonPath('created', 0)->assertJsonPath('skipped.0.reason', 'Sudah ada di Kinetik.');
});

it('refuses an RK of another team', function () {
    [, , , $member] = importSetup();
    $admin = adminUser();
    Employee::factory()->create(['user_id' => $admin->id]);
    $other = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => Team::factory()->create()->id]);

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [[
        'employee_id' => $member->id, 'performance_plan_id' => $other->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30',
    ]]])->assertJsonPath('created', 0);
});

it('keeps the import to admins', function () {
    $this->actingAs(staffUser())->get(route('admin.import-kegiatan'))->assertForbidden();
    $this->actingAs(headUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow()]])->assertForbidden();
    $this->actingAs(adminUser())->get(route('admin.import-kegiatan'))->assertOk();
});

it('matches names the way Kinetik stores them, with the titles glued to the name', function () {
    [$team] = importSetup();
    $stored = Employee::factory()->create(['name' => 'Yudha Subakti S.Si.', 'display_name' => 'Yudha Subakti S.Si.', 'is_active' => true]);
    $stored->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $muhammad = Employee::factory()->create(['name' => 'Muhammad Zein SST, M.P.W', 'is_active' => true]);
    $muhammad->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['member' => 'Yudha Subakti, S.Si']),
        sheetRow(['member' => 'Dr. Muhammad Zein, SST, M.P.W.P']),
        sheetRow(['member' => 'Maya Novita Sari, S. Tr. Stat']),
    ]])->json('rows');

    expect($rows[0]['employee_id'])->toBe($stored->id)->and($rows[1]['employee_id'])->toBe($muhammad->id)->and($rows[2]['employee_id'])->toBeNull();
});

it('matches by NIP when the row has one, and never falls back to the name', function () {
    [$team] = importSetup();
    $a = Employee::factory()->create(['name' => 'Budi Santoso', 'nip_baru' => '199001012015011001', 'nip_lama' => '340000001', 'is_active' => true]);
    $b = Employee::factory()->create(['name' => 'Budi Santoso', 'nip_baru' => '199202022016021002', 'nip_lama' => '340000002', 'is_active' => true]);
    foreach ([$a, $b] as $e) {
        $e->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    }

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['member' => 'Budi Santoso, SST', 'nip' => '199202022016021002']),   // two people share the name: the NIP decides
        sheetRow(['member' => 'Budi Santoso, SST']),                                    // no NIP: the name is ambiguous
        sheetRow(['member' => 'Budi Santoso, SST', 'nip' => '340000001']),             // the short NIP BPS works too
        sheetRow(['member' => 'Aditya Saputra, SST', 'nip' => '111111111111111111']),   // unknown NIP: no guessing by name
    ]])->json('rows');

    expect($rows[0]['employee_id'])->toBe($b->id)->and($rows[1]['employee_id'])->toBeNull()->and($rows[2]['employee_id'])->toBe($a->id)
        ->and($rows[3]['employee_id'])->toBeNull()->and($rows[3]['notes'][0])->toContain('NIP 111111111111111111');
});

it('falls back to the RK of the member teams when the matched projek has none', function () {
    [$team, , $plan, $member] = importSetup();
    $empty = Project::factory()->create(['team_id' => $team->id, 'name' => 'Pengembangan Inovasi', 'year' => (int) config('kinetik.kip.tahun')]);

    $row = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow(['project' => 'Pengembangan Inovasi'])]])->json('rows.0');

    expect($row['project_id'])->toBe($empty->id)->and($row['blocked'])->toBeFalse()->and(collect($row['rk_options'])->pluck('id')->all())->toContain($plan->id);
});

it('never pushes imported kegiatan to kipApp by itself', function () {
    [, , $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Diimpor dari Excel mingguan', 'date_start' => now('Asia/Makassar')->toDateString(), 'date_end' => now('Asia/Makassar')->toDateString(), 'source' => 'import', 'status' => 'planned']);
    $normal = PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Dibuat sendiri oleh pegawai', 'date_start' => now('Asia/Makassar')->toDateString(), 'date_end' => now('Asia/Makassar')->toDateString(), 'source' => 'member', 'status' => 'planned']);

    $spy = new class extends PushPlanItemAction
    {
        public array $seen = [];

        public function __construct() {}

        public function execute(PlanItem $plan, bool $force = false): bool
        {
            $this->seen[] = $plan->description;

            return true;
        }
    };
    (new PushDuePlansAction($spy))->execute();

    expect($spy->seen)->toBe([$normal->description]);
});

it('splits a kegiatan that crosses a quarter, and a month-long one shows in every week', function () {
    [, , $plan, $member] = importSetup();
    $admin = adminUser();

    $rows = $this->actingAs($admin)->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['date_start' => '2026-09-21', 'date_end' => '2026-10-09']),
    ]])->json('rows');

    expect($rows)->toHaveCount(2)
        ->and([$rows[0]['date_start'], $rows[0]['date_end'], $rows[0]['target']])->toEqual(['2026-09-21', '2026-09-30', 4])
        ->and([$rows[1]['date_start'], $rows[1]['date_end'], $rows[1]['target']])->toEqual(['2026-10-01', '2026-10-09', null]);

    // A full-month kegiatan is one item that every week of the month lists.
    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [[
        'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Pengembangan aplikasi sepanjang Oktober',
        'date_start' => '2026-10-01', 'date_end' => '2026-10-30',
    ]]])->assertJsonPath('created', 1);

    $user = User::factory()->create();
    $member->update(['user_id' => $user->id]);
    $user->assignRole('staff');
    foreach (['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'] as $week) {
        $this->actingAs($user)->get(route('weekly.index', ['week' => $week]))->assertInertia(fn ($page) => $page->has('thisWeek', 1));
    }
});

it('shows an RK once even when kipApp holds it under several projek, and matches a projek by shared words', function () {
    [$team, $project, $plan, $member] = importSetup();
    $other = Project::factory()->create(['team_id' => $team->id, 'name' => 'Proyek Lain', 'year' => (int) config('kinetik.kip.tahun')]);
    PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id, 'description' => 'MENYUSUN  publikasi PDRB']);
    $project->update(['name' => 'Manajemen Mitra Statistik']);

    $row = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow(['project' => 'Manajemen Mitra'])]])->json('rows.0');
    expect($row['rk_options'])->toHaveCount(1)->and($row['project_id'])->toBe($project->id)->and($other->id)->not->toBe($row['project_id']);

    $none = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow(['project' => 'Penyusunan Daftar Risiko'])]])->json('rows.0');
    expect($none['notes'])->toContain('Projek "Penyusunan Daftar Risiko" tidak ditemukan di tim pegawai ini. Pilih RK yang sesuai secara manual.');
});

it('does not tick a row that looks like a kegiatan already in kipApp, and refuses it at save unless ignore_similar', function () {
    [, $project, $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Menyiapkan kuesioner PDRB triwulanan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'kipapp', 'status' => 'done', 'kip_external_id' => 'k1']);
    $admin = adminUser();

    // the sheet words it differently and puts it on the next day
    $row = sheetRow(['description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-16', 'date_end' => '2026-10-16']);
    $preview = $this->actingAs($admin)->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-12', 'rows' => [$row]])->json('rows.0');

    expect($preview['duplicate'])->toBeFalse()->and($preview['blocked'])->toBeFalse()
        ->and($preview['similar']['in_kipapp'])->toBeTrue()->and($preview['similar']['date'])->toBe('2026-10-15')
        ->and(implode(' ', $preview['notes']))->toContain('Mirip dengan kegiatan di kipApp');

    $save = fn (array $extra) => $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [[
        'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-16', 'date_end' => '2026-10-16', ...$extra,
    ]]])->json();

    expect($save([])['created'])->toBe(0)->and($save([])['skipped'][0]['reason'])->toContain('Mirip dengan kegiatan di kipApp');
    expect($save(['ignore_similar' => true])['created'])->toBe(1);
});

it('keeps numbered kegiatan apart: BRS 2 and BRS 5 are two kegiatan', function () {
    $finder = new KegiatanDuplicateFinder;

    expect($finder->similarity('Mengikuti BRS 2', 'Mengikuti BRS 5'))->toBe(0.0)
        ->and($finder->similarity('Mengikuti  BRS 2.', 'mengikuti brs 2'))->toBe(1.0)
        ->and($finder->similarity('Penyiapan kuesioner PDRB triwulanan', 'Menyiapkan kuesioner PDRB triwulanan'))->toBeGreaterThan(0.8);
});

it('holds an imported kegiatan back from kipApp when a similar one is already there, until "Kirim tetap"', function () {
    [, , $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Menyiapkan kuesioner PDRB triwulanan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'kipapp', 'status' => 'done', 'kip_external_id' => 'k1']);
    $imported = PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'import', 'status' => 'planned']);

    $sent = app(PushPlanItemAction::class)->execute($imported->load('employee'));

    expect($sent)->toBeFalse()->and($imported->fresh()->push_error)->toStartWith(PushPlanItemAction::SIMILAR_PREFIX)
        ->and($imported->fresh()->push_attempts)->toBe(0);   // not a failure: no notice, no retry count
});

it('ranks the RK of the matched projek by the words of the kegiatan and picks the clear winner, saying so', function () {
    [$team, $project, $plan, $member] = importSetup();
    $other = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id, 'description' => 'Menyelenggarakan rapat koordinasi tim']);
    PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id, 'description' => 'Pengelolaan arsip dokumen kegiatan']);

    $row = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['description' => 'Penyusunan publikasi PDRB triwulan III']),
    ]])->json('rows.0');

    expect($row['performance_plan_id'])->toBe($plan->id)                       // "publikasi PDRB" is covered by this RK only
        ->and($row['rk_options'][0]['id'])->toBe($plan->id)->and($row['rk_options'][0]['score'])->toBeGreaterThan($row['rk_options'][1]['score'])
        ->and(implode(' ', $row['notes']))->toContain('RK dipilih dari kemiripan dengan uraian');

    // nothing stands out: the admin chooses
    $vague = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow(['description' => 'Mengerjakan tugas harian kantor'])]])->json('rows.0');
    expect($vague['performance_plan_id'])->toBeNull()->and(count($vague['rk_options']))->toBe(3);
});

it('offers and saves an RK that belongs to the person alone, such as one of a Pusat team', function () {
    [, $project, , $member] = importSetup();
    $pusat = PerformancePlan::factory()->create(['team_id' => null, 'project_id' => null, 'description' => 'Terselenggaranya Layanan Kesehatan Mental Untuk Pegawai', 'owner_employee_id' => $member->id, 'is_additional' => false]);
    $admin = adminUser();

    $row = $this->actingAs($admin)->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['description' => 'Layanan kesehatan mental untuk pegawai bulan Oktober', 'project' => 'Proyek Pusat']),
    ]])->json('rows.0');
    expect(collect($row['rk_options'])->pluck('id'))->toContain($pusat->id);

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [[
        'employee_id' => $member->id, 'performance_plan_id' => $pusat->id, 'description' => 'Layanan kesehatan mental untuk pegawai bulan Oktober', 'date_start' => '2026-10-20', 'date_end' => '2026-10-20',
    ]]])->assertOk()->assertJsonPath('created', 1);

    expect(PlanItem::where('performance_plan_id', $pusat->id)->first()->team_id)->toBe($member->teams()->first()->id);
});

it('holds a kegiatan typed in Kinetik back from kipApp only when a near-identical one is already there', function () {
    [, , $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Mengkoordinasikan penyusunan monitoring mingguan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'kipapp', 'status' => 'done', 'kip_external_id' => '8800001']);
    $same = PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Mengkoordinasikan penyusunan monitoring mingguan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'member']);
    $other = PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Mengkoordinasikan penyusunan laporan bulanan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'member']);

    expect(app(PushPlanItemAction::class)->execute($same->load('employee')))->toBeFalse()
        ->and($same->fresh()->push_error)->toStartWith(PushPlanItemAction::SIMILAR_PREFIX);

    // A different piece of work on the same day is not held for similarity (it fails later only for lack of kipApp setup in this test).
    app(PushPlanItemAction::class)->execute($other->load('employee'));
    expect((string) $other->fresh()->push_error)->not->toStartWith(PushPlanItemAction::SIMILAR_PREFIX);
});
