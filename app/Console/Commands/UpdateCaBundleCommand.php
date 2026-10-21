<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Replaces the shipped CA bundle with the current one from curl.se, only when its published checksum matches. */
class UpdateCaBundleCommand extends Command
{
    protected $signature = 'kinetik:update-ca-bundle';

    protected $description = 'Download the current Mozilla CA bundle (curl.se) and replace the shipped one after checking its checksum';

    public function handle(): int
    {
        $target = (string) config('kinetik.kip.ca_bundle');
        if ($target === '') {
            $this->error('kinetik.kip.ca_bundle is empty.');

            return self::FAILURE;
        }

        // The download goes over the bundle that is already in place, or the server's own when there is none yet.
        $pem = Http::timeout(60)->get('https://curl.se/ca/cacert.pem');
        $sum = Http::timeout(30)->get('https://curl.se/ca/cacert.pem.sha256');
        if (! $pem->successful() || ! $sum->successful()) {
            $this->error('Could not download the bundle or its checksum. The current file is unchanged.');

            return self::FAILURE;
        }

        $expected = strtok(trim($sum->body()), ' ');
        if (! hash_equals((string) $expected, hash('sha256', $pem->body())) || substr_count($pem->body(), 'BEGIN CERTIFICATE') < 50) {
            $this->error('Checksum mismatch or too few certificates. The current file is unchanged.');

            return self::FAILURE;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        file_put_contents($target.'.tmp', $pem->body());
        rename($target.'.tmp', $target);
        $this->info('CA bundle updated: '.substr_count($pem->body(), 'BEGIN CERTIFICATE').' certificates.');

        return self::SUCCESS;
    }
}
