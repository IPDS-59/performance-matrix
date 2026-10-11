<?php

namespace App\Actions\Kinetik;

use App\Models\KipCredential;
use App\Models\User;
use App\Notifications\KinetikNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Tell the admins, once per token and state, that the kipApp token expires
 * soon or has expired. Every kipApp sync stops when it expires.
 */
class AlertKipTokenExpiryAction
{
    /** @return string|null the state alerted ("expiring" or "expired"), null when nothing was sent */
    public function execute(): ?string
    {
        $credential = KipCredential::current();
        $state = match (true) {
            $credential === null => null,
            $credential->isExpired() => 'expired',
            $credential->isExpiringSoon(3) => 'expiring',
            default => null,
        };

        // Cache::add is false when the key exists: this alert went out already.
        if ($state === null || ! Cache::add("kip-token-alert:{$credential->id}:{$state}", true, now()->addDays(7))) {
            return null;
        }

        $at = $credential->expires_at->timezone(config('app.timezone'))->locale('id')->translatedFormat('j M Y H:i');
        $message = $state === 'expired'
            ? "Token kipApp kedaluwarsa sejak {$at}. Semua sinkronisasi kipApp berhenti sampai token baru disimpan."
            : "Token kipApp kedaluwarsa pukul {$at}. Simpan token baru sebelum itu agar sinkronisasi tidak berhenti.";

        User::permission('manage-kip-integration')->get()
            ->each(fn (User $admin) => $admin->notify(new KinetikNotification("kip_token_{$state}", $message, route('kip-integration.index'))));

        return $state;
    }
}
