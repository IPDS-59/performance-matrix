<?php

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\KipCredential;
use Illuminate\Support\Facades\Http;

/**
 * Build a kipApp-style JWT (header.payload.sig) with the given payload.
 * Only the payload segment matters — KipTokenInfo never verifies the signature.
 */
function fakeKipToken(array $payload): string
{
    $b64 = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');

    return $b64(['typ' => 'JWT', 'alg' => 'HS256']).'.'.$b64($payload).'.sig';
}

// ── Access control ────────────────────────────────────────────────────────

it('redirects guests to login', function () {
    $this->get(route('kip-integration.index'))->assertRedirect(route('login'));
});

it('forbids non-admin users', function () {
    $this->actingAs(staffUser())
        ->get(route('kip-integration.index'))
        ->assertForbidden();
});

it('renders the integration page for an admin', function () {
    $this->actingAs(adminUser())
        ->get(route('kip-integration.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/KipIntegration')
            ->where('credential', null)
            ->has('stats')
        );
});

// ── Store token ───────────────────────────────────────────────────────────

it('stores the token and decodes its account + expiry', function () {
    $exp = now()->addDay()->timestamp;
    $token = fakeKipToken(['nip' => '340060924', 'email' => 'sukma.nirmala@bps.go.id', 'exp' => $exp]);

    $this->actingAs(adminUser())
        ->post(route('kip-integration.token'), ['token' => 'Bearer '.$token])
        ->assertRedirect()
        ->assertSessionHas('success');

    $cred = KipCredential::current();
    expect($cred)->not->toBeNull();
    expect($cred->account_nip)->toBe('340060924');
    expect($cred->account_name)->toBe('sukma.nirmala@bps.go.id');
    expect($cred->expires_at->timestamp)->toBe($exp);
    // Stored encrypted but readable via the cast; "Bearer " stripped.
    expect($cred->token)->toBe($token);
});

it('validates that a token is required', function () {
    $this->actingAs(adminUser())
        ->post(route('kip-integration.token'), [])
        ->assertSessionHasErrors(['token']);
});

// ── Authenticator resolution ──────────────────────────────────────────────

it('makes the authenticator prefer the stored credential over config', function () {
    config(['kinetik.kip.token' => 'env-token']);

    KipCredential::create(['token' => 'db-token']);

    $request = app(ConfigBearerAuthenticator::class)
        ->apply(Http::baseUrl('http://x'));

    expect($request->getOptions()['headers']['x-auth'] ?? null)->toBe('Bearer db-token');
});

// ── Centralized sync ──────────────────────────────────────────────────────

it('syncs all active employees with a nip_lama', function () {
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);

    Employee::factory()->count(2)->create([
        'is_active' => true,
        'nip_lama' => fn () => fake()->unique()->numerify('3400#####'),
    ]);
    Employee::factory()->create(['is_active' => true, 'nip_lama' => null]); // skipped

    $this->actingAs(adminUser())
        ->post(route('kip-integration.sync'))
        ->assertRedirect()
        ->assertSessionHas('success');

    // MockKipActivitySource returns 3 activities per employee → 2 employees = 6
    expect(KipActivity::count())->toBe(6);
});

it('blocks sync when no token is configured', function () {
    config(['kinetik.kip.token' => null]);

    $this->actingAs(adminUser())
        ->post(route('kip-integration.sync'))
        ->assertRedirect()
        ->assertSessionHas('error');
});
