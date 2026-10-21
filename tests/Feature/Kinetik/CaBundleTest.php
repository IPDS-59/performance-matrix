<?php

use Illuminate\Support\Facades\Http;

it('ships a current CA bundle and the HTTP client verifies against it', function () {
    $bundle = config('kinetik.kip.ca_bundle');

    expect(is_file($bundle))->toBeTrue()
        ->and(substr_count(file_get_contents($bundle), 'BEGIN CERTIFICATE'))->toBeGreaterThan(100);

    $factory = Http::getFacadeRoot();
    $options = (new ReflectionProperty($factory, 'globalOptions'))->getValue($factory);
    expect($options['verify'] ?? null)->toBe($bundle);
});

it('keeps the old bundle when the checksum does not match', function () {
    $file = sys_get_temp_dir().'/cacert-test.pem';
    file_put_contents($file, 'old');
    config(['kinetik.kip.ca_bundle' => $file]);
    Http::fake([
        'curl.se/ca/cacert.pem.sha256' => Http::response(str_repeat('0', 64).'  cacert.pem'),
        'curl.se/ca/cacert.pem' => Http::response(str_repeat("-----BEGIN CERTIFICATE-----\nx\n", 60)),
    ]);

    $this->artisan('kinetik:update-ca-bundle')->assertFailed();

    expect(file_get_contents($file))->toBe('old');
});

it('replaces the bundle when the checksum matches', function () {
    $file = sys_get_temp_dir().'/cacert-test2.pem';
    file_put_contents($file, 'old');
    config(['kinetik.kip.ca_bundle' => $file]);
    $pem = str_repeat("-----BEGIN CERTIFICATE-----\nx\n", 60);
    Http::fake([
        'curl.se/ca/cacert.pem.sha256' => Http::response(hash('sha256', $pem).'  cacert.pem'),
        'curl.se/ca/cacert.pem' => Http::response($pem),
    ]);

    $this->artisan('kinetik:update-ca-bundle')->assertSuccessful();

    expect(file_get_contents($file))->toBe($pem);
});
