<?php

use App\Actions\Kinetik\BuildDataIntegrityAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Models\BugReport;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Notifications\KinetikNotification;
use Illuminate\Support\Facades\Notification;

it('lets any signed-in user report a problem and tells the admins', function () {
    Notification::fake();
    $admin = adminUser();
    $staff = User::factory()->create();

    $this->actingAs($staff)->post(route('bug-reports.store'), ['title' => 'Projek tidak muncul', 'description' => 'Setelah memilih RK, kolom projek tetap kosong.', 'page_url' => 'https://kinetik.test/rekap-mingguan'])
        ->assertRedirect();

    expect(BugReport::first())->user_id->toBe($staff->id)->status->toBe('baru');
    Notification::assertSentTo($admin, KinetikNotification::class, fn ($n) => $n->type === 'bug_report');
    $this->actingAs($staff)->post(route('bug-reports.store'), ['title' => 'x', 'description' => 'pendek'])->assertSessionHasErrors(['title', 'description']);
});

it('keeps the report list and its status to admins', function () {
    $report = BugReport::create(['user_id' => null, 'title' => 'Judul laporan', 'description' => 'Isi laporan yang cukup panjang.']);

    $this->actingAs(User::factory()->create())->get(route('admin.bug-reports'))->assertForbidden();
    $this->actingAs(adminUser())->get(route('admin.bug-reports'))->assertOk();
    $this->actingAs(adminUser())->put(route('admin.bug-reports.update', $report), ['status' => 'selesai', 'admin_note' => 'Sudah diperbaiki.'])->assertRedirect();

    expect($report->fresh()->status)->toBe('selesai');
});

it('lists RK and kegiatan that lack the projek kipApp needs', function () {
    $team = Team::factory()->create();
    Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'Leader']);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => null, 'description' => 'RK tanpa projek']);
    $member = Employee::factory()->create();
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan tanpa projek', 'date_start' => '2026-10-26', 'date_end' => '2026-10-26']);

    $checks = collect(app(BuildDataIntegrityAction::class)->execute())->keyBy('key');

    expect($checks['rk_no_project']['count'])->toBe(1)
        ->and($checks['items_no_project']['count'])->toBe(1)
        ->and($checks['items_no_rk']['count'])->toBe(0);
    $this->actingAs(adminUser())->get(route('admin.data-integrity'))->assertOk();
});

it('holds a kegiatan back from kipApp while its RK has no projek', function () {
    seedRolesAndPermissions();
    $team = Team::factory()->create();
    Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'Leader']);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => null]);
    $member = Employee::factory()->create(['kip_pegawai_id' => '1', 'user_id' => User::factory()->create()->id]);
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan tanpa projek', 'date_start' => '2026-10-26', 'date_end' => '2026-10-26']);

    expect(app(PushPlanItemAction::class)->execute($item))->toBeFalse();
    expect($item->fresh()->push_error)->toContain('belum punya projek');
});
