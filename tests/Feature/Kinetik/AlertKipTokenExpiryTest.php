<?php

use App\Actions\Kinetik\AlertKipTokenExpiryAction;
use App\Models\KipCredential;
use Illuminate\Support\Facades\Cache;

function credentialExpiringAt($when): KipCredential
{
    return KipCredential::create(['token' => 'x.y.z', 'account_nip' => '340060924', 'expires_at' => $when]);
}

it('alerts the admins once when the token is about to expire, and once more when it has expired', function () {
    $admin = adminUser();
    $staff = staffUser();
    $credential = credentialExpiringAt(now()->addHours(2));
    $alert = app(AlertKipTokenExpiryAction::class);

    expect($alert->execute())->toBe('expiring')
        ->and($alert->execute())->toBeNull();

    $credential->update(['expires_at' => now()->subMinute()]);
    expect($alert->execute())->toBe('expired');

    expect($admin->notifications()->pluck('data')->pluck('type')->sort()->values()->all())->toBe(['kip_token_expired', 'kip_token_expiring'])
        ->and($admin->notifications()->first()->data['url'])->toContain('/integrasi-kipapp')
        ->and($staff->notifications()->count())->toBe(0);
});

it('stays quiet while the token is valid for more than three hours', function () {
    adminUser();
    credentialExpiringAt(now()->addHours(20));

    expect(app(AlertKipTokenExpiryAction::class)->execute())->toBeNull();
});

it('runs the check on normal app use, at most every ten minutes', function () {
    $admin = adminUser();
    credentialExpiringAt(now()->subHour());
    Cache::forget('kip-token-check');

    $this->actingAs($admin)->get(route('notifications.page'))->assertOk();

    expect($admin->notifications()->count())->toBe(1)
        ->and(Cache::has('kip-token-check'))->toBeTrue();
});
