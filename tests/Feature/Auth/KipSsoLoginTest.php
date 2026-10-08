<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KipSsoLoginTest extends TestCase
{
    use RefreshDatabase;

    private const SSO_FORM = 'https://sso.bps.go.id/auth/realms/pegawai-bps/login-actions/authenticate?session_code=a&amp;execution=b';

    protected function setUp(): void
    {
        parent::setUp();
        seedRolesAndPermissions();
        config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['email' => 'sukma@bps.go.id', 'password' => 'password']);
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function fakeSso(string $outcome): void
    {
        $jwt = 'h.'.rtrim(strtr(base64_encode(json_encode(['exp' => time() + 86400])), '+/', '-_'), '=').'.s';

        Http::fake([
            '*/login' => Http::response('<form id="kc-form-login" action="'.self::SSO_FORM.'">', 200),
            'sso.bps.go.id/*' => match ($outcome) {
                'ok' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/api/login?code=x']),
                'rejected' => Http::response('<form action="x">Invalid username or password.', 200),
                default => Http::response('boom', 503),
            },
            'kipapp.bps.go.id/api/login?*' => Http::response('', 302, ['Location' => 'https://kipapp.bps.go.id/#/auth/process?t='.$jwt]),
        ]);
    }

    public function test_staff_log_in_with_their_sso_password_and_the_credential_is_stored_encrypted(): void
    {
        $this->fakeSso('ok');
        $user = $this->staff();

        $this->post('/login', ['email' => 'sukma', 'password' => 'sso-secret'])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $stored = MemberKipCredential::firstWhere('user_id', $user->id);
        $this->assertSame('sso-secret', $stored->plainPassword());
        $this->assertNotNull($stored->plainToken());
        $this->assertStringNotContainsString('sso-secret', (string) json_encode($stored->getRawOriginal()));
        $this->assertStringNotContainsString('sso-secret', $stored->toJson());
    }

    public function test_first_login_with_the_default_password_works_without_calling_bps_and_asks_to_connect(): void
    {
        Http::fake();
        $user = $this->staff();

        $this->post('/login', ['email' => 'sukma@bps.go.id', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
        $this->get('/dashboard')->assertRedirect(route('kip.connect'));
        $this->get(route('kip.connect'))->assertOk();
    }

    public function test_connecting_stores_the_sso_password_and_retires_the_default_one(): void
    {
        $this->fakeSso('ok');
        $user = $this->staff();
        $this->actingAs($user);

        $this->post(route('kip.connect.store'), ['password' => 'sso-secret'])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame('sso-secret', $user->memberKipCredential->plainPassword());
        $this->get('/dashboard')->assertOk();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('sso-secret', $fresh->password));
        $this->assertFalse(Hash::check('password', $fresh->password));
        $this->assertFalse($fresh->needsKipConnection());
    }

    public function test_a_wrong_sso_password_is_rejected_on_the_connect_page(): void
    {
        $this->fakeSso('rejected');
        $user = $this->staff();

        $this->actingAs($user)->post(route('kip.connect.store'), ['password' => 'nope'])->assertSessionHasErrors('password');

        $this->assertFalse($user->memberKipCredential()->exists());
    }

    public function test_after_the_deadline_the_default_password_is_refused(): void
    {
        config(['kinetik.kip.default_password_until' => now()->subDay()->toDateString()]);
        $this->fakeSso('rejected');
        $this->staff();

        $this->post('/login', ['email' => 'sukma', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_connected_staff_and_admins_are_not_redirected(): void
    {
        $this->fakeSso('ok');
        $user = $this->staff();
        $this->actingAs($user)->post(route('kip.connect.store'), ['password' => 'sso-secret']);

        $this->actingAs(adminUser())->get('/dashboard')->assertOk();
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_a_synced_password_still_works_when_sso_is_down(): void
    {
        $this->fakeSso('ok');
        $user = $this->staff();
        $this->post('/login', ['email' => 'sukma', 'password' => 'sso-secret']);
        $this->post('/logout');

        $this->fakeSso('down');
        $this->post('/login', ['email' => 'sukma', 'password' => 'sso-secret'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_unsynced_account_cannot_log_in_when_sso_is_down(): void
    {
        $this->fakeSso('down');
        $this->staff();

        $this->post('/login', ['email' => 'sukma', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admins_never_use_sso_even_with_an_sso_style_login(): void
    {
        Http::fake();
        $admin = adminUser();
        $admin->update(['email' => 'boss@bps.go.id']);
        Employee::factory()->create(['user_id' => $admin->id]);

        $this->post('/login', ['email' => 'boss@bps.go.id', 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        Http::assertNothingSent();
    }

    public function test_accounts_without_an_sso_username_keep_their_local_password(): void
    {
        Http::fake();
        $admin = User::factory()->create(['email' => 'admin@bpssulteng.id']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        Http::assertNothingSent();
    }
}
