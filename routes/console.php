<?php

use App\Actions\Kinetik\AlertKipTokenExpiryAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kinetik: pull kipApp activities daily at 05:00 (Probis item 5)
Schedule::command('kinetik:sync-kip-activities')->dailyAt('05:00');

// Kinetik: mirror kipApp structure (teams/projects/members) daily at 04:30
Schedule::command('kinetik:sync-kip-structure')->dailyAt('04:30');

// Kinetik: keep only BPS Provinsi Sulawesi Tengah staff, after the structure sync
Schedule::command('kinetik:verify-office --apply')->dailyAt('04:40');

// Kinetik: enrich RK with team + parsed IKI targets daily at 04:45
Schedule::command('kinetik:sync-kip-plans')->dailyAt('04:45');

// Angka Kredit: golongan and SKP predikat change a few times a year. About
// three kipApp calls per employee, so once a week is enough.
Schedule::command('kinetik:sync-careers')->weeklyOn(1, '05:30');

// Warn the admins before the kipApp token expires. The CheckKipTokenExpiry
// middleware does the same on page use, for servers without cron.
Schedule::call(fn () => app(AlertKipTokenExpiryAction::class)->execute())
    ->name('kinetik:alert-kip-token')
    ->everyTenMinutes();
