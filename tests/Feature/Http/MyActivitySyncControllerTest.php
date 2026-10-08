<?php

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\KipCredential;
use App\Models\MemberKipCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = staffUser();
    $this->employee = Employee::factory()->create(['user_id' => $this->user->id, 'is_active' => true, 'nip_lama' => '340099999']);
    RateLimiter::clear('sync-mine:'.$this->user->id);
});

it('redirects guests to login', function () {
    $this->post(route('my-sync'))->assertRedirect(route('login'));
});

it('syncs only the signed-in member', function () {
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);
    Employee::factory()->create(['is_active' => true, 'nip_lama' => '340011111']);

    $this->actingAs($this->user)->post(route('my-sync'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(KipActivity::count())->toBe(3)
        ->and(KipActivity::where('employee_id', $this->employee->id)->count())->toBe(3);
});

it('refuses a second sync within a minute', function () {
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);

    $this->actingAs($this->user)->post(route('my-sync'));
    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('error');

    expect(KipActivity::count())->toBe(3);
});

it('tells a member without a NIP Lama what to do', function () {
    $this->employee->update(['nip_lama' => null]);

    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('error');
});

it('blocks the sync when no token exists at all', function () {
    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('error');
});

it('uses the member own kipApp token when it is still valid', function () {
    config([
        'kinetik.kip.source' => 'api',
        'kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
    ]);
    MemberKipCredential::remember($this->user, 'pw', 'member-token', now()->addHours(5));
    Http::fake(['*' => Http::response([], 200)]);

    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('success');

    Http::assertSent(fn ($request) => $request->hasHeader('x-auth', 'Bearer member-token'));
});

it('renews an expired member token with the stored SSO password', function () {
    config([
        'kinetik.kip.source' => 'api',
        'kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
    ]);
    $this->user->update(['email' => 'ani@bps.go.id']);
    MemberKipCredential::remember($this->user, 'sso-pass', 'old-token', now()->subHour());
    $jwt = 'h.'.rtrim(strtr(base64_encode(json_encode(['exp' => time() + 86400])), '+/', '-_'), '=').'.s';
    Http::fake([
        '*/login' => Http::response('<form id="kc-form-login" action="https://sso.bps.go.id/x">', 200),
        'sso.bps.go.id/*' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/api/login?code=x']),
        'kipapp.bps.go.id/api/login?*' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/#/auth/process?t='.$jwt]),
        '*' => Http::response([], 200),
    ]);

    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('success');

    Http::assertSent(fn ($request) => $request->hasHeader('x-auth', 'Bearer '.$jwt));
    expect($this->user->fresh()->memberKipCredential->hasValidToken())->toBeTrue();
});

it('still syncs when the cache cannot be written', function () {
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);
    RateLimiter::shouldReceive('tooManyAttempts')->andThrow(new ErrorException('fopen(): Failed to open stream'));

    $this->actingAs($this->user)->post(route('my-sync'))->assertSessionHas('success');

    expect(KipActivity::count())->toBe(3);
});
