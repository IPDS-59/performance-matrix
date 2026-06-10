<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kinetik: pull unsent kipApp activities every Monday at 05:00
Schedule::command('kinetik:sync-kip-activities')->weeklyOn(1, '05:00');

// Kinetik: mirror kipApp structure (teams/projects/members) every Monday at 04:30
Schedule::command('kinetik:sync-kip-structure')->weeklyOn(1, '04:30');
