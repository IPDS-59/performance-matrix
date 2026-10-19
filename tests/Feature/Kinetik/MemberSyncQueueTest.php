<?php

use App\Actions\Kinetik\ConnectKipAccountAction;
use App\Jobs\SyncMemberDataJob;
use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\MemberSyncWait;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    seedRolesAndPermissions();
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.source' => 'mock', 'kinetik.kip.token' => null]);
});

function syncMember(bool $withToken = true, string $nip = '340060924'): Employee
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'nip_lama' => $nip, 'kip_pegawai_id' => '777', 'is_active' => true]);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', $withToken ? now()->addHours(5) : now()->subHour());

    return $employee;
}

it('queues the own sync of every member who has a stored login', function () {
    Queue::fake();
    $a = syncMember(nip: '111111111');
    $b = syncMember(nip: '222222222');
    Employee::factory()->create(['nip_lama' => '333333333', 'is_active' => true]); // never connected
    Employee::factory()->create(['nip_lama' => '444444444', 'is_active' => false, 'user_id' => User::factory()->create()->id]);

    $this->artisan('kinetik:sync-members')->assertSuccessful();

    Queue::assertPushed(SyncMemberDataJob::class, 2);
    Queue::assertPushed(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $a->id);
    Queue::assertPushed(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $b->id);
});

it('does not try a member who has to sign in again, and retries only the SSO waits on --retry-sso', function () {
    Queue::fake();
    $needsLogin = syncMember(nip: '111111111');
    $ssoDown = syncMember(nip: '222222222');
    $fine = syncMember(nip: '333333333');
    MemberSyncWait::park($needsLogin->id, MemberSyncWait::LOGIN_REQUIRED, 'x');
    MemberSyncWait::park($ssoDown->id, MemberSyncWait::SSO_DOWN, 'x');

    $this->artisan('kinetik:sync-members')->assertSuccessful();
    Queue::assertPushed(SyncMemberDataJob::class, 2);
    Queue::assertNotPushed(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $needsLogin->id);

    Queue::fake();
    $this->artisan('kinetik:sync-members --retry-sso')->assertSuccessful();
    Queue::assertPushed(SyncMemberDataJob::class, 1);
    Queue::assertPushed(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $ssoDown->id);
    Queue::assertNotPushed(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $fine->id);
});

it('parks a member whose login was rejected, tells them once, and does not touch the shared token', function () {
    $e = syncMember(withToken: false);
    Http::fake([
        '*/login' => Http::response('<form id="kc-form-login" action="https://sso.bps.go.id/x">', 200),
        'sso.bps.go.id/*' => Http::response('<form action="x">Invalid username or password.', 200),
    ]);

    SyncMemberDataJob::dispatchSync($e->id);
    SyncMemberDataJob::dispatchSync($e->id);

    $wait = MemberSyncWait::where('employee_id', $e->id)->first();
    expect($wait)->reason->toBe(MemberSyncWait::LOGIN_REQUIRED)->attempts->toBeGreaterThanOrEqual(1);
    expect($e->user->fresh()->notifications->where('data.type', 'sync_login_required'))->toHaveCount(1);
});

it('waits for SSO when BPS SSO cannot be reached, without telling the member', function () {
    $e = syncMember(withToken: false);
    Http::fake(['*' => Http::response('boom', 503)]);

    SyncMemberDataJob::dispatchSync($e->id);

    expect(MemberSyncWait::where('employee_id', $e->id)->first())->reason->toBe(MemberSyncWait::SSO_DOWN);
    expect($e->user->fresh()->notifications)->toHaveCount(0);
});

it('clears the wait when a sync succeeds', function () {
    $e = syncMember();
    MemberSyncWait::park($e->id, MemberSyncWait::LOGIN_REQUIRED, 'x');

    SyncMemberDataJob::dispatchSync($e->id);

    expect(MemberSyncWait::where('employee_id', $e->id)->exists())->toBeFalse();
});

it('runs the member\'s own sync again right after the member signs in', function () {
    Bus::fake();
    $e = syncMember(withToken: false);
    Http::fake([
        '*/login' => Http::response('<form id="kc-form-login" action="https://sso.bps.go.id/auth/realms/pegawai-bps/login-actions/authenticate?x=1">', 200),
        'sso.bps.go.id/*' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/api/login?code=x']),
        'kipapp.bps.go.id/api/login?*' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/#/auth/process?t=h.'.rtrim(strtr(base64_encode(json_encode(['exp' => time() + 86400, 'nip' => '340060924'])), '+/', '-_'), '=').'.s']),
    ]);

    app(ConnectKipAccountAction::class)->handle($e->user, 'sso-pass');

    Bus::assertDispatchedAfterResponse(SyncMemberDataJob::class, fn ($job) => $job->employeeId === $e->id);
});
