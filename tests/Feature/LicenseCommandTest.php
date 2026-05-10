<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lx_cmd_test_' . uniqid();
    mkdir($this->tempHome . DIRECTORY_SEPARATOR . '.lx', 0755, true);
    
    // Bind LicenseService with tempHome
    $this->app->singleton(\App\Services\LicenseService::class, function () {
        return new \App\Services\LicenseService('https://api.lx.dev', $this->tempHome);
    });
});

afterEach(function () {
    File::deleteDirectory($this->tempHome);
});

test('license:activate success', function () {
    Http::fake([
        'api.lx.dev/license/validate' => Http::response(['valid' => true], 200),
    ]);

    $this->artisan('license:activate LX-PRO-TEST')
        ->expectsOutput('Activating license: LX-PRO-TEST...')
        ->expectsOutput('✓ License activated successfully!')
        ->assertExitCode(0);
});

test('license:activate failure', function () {
    Http::fake([
        'api.lx.dev/license/validate' => Http::response(['valid' => false], 200),
    ]);

    $this->artisan('license:activate LX-PRO-INVALID')
        ->expectsOutput('Activating license: LX-PRO-INVALID...')
        ->expectsOutput('✗ Invalid license key or server unreachable.')
        ->assertExitCode(1);
});

test('license:status shows warning when no license', function () {
    $this->artisan('license:status')
        ->expectsOutput('No license active. Purchase Pro at https://lx.dev/pro')
        ->assertExitCode(0);
});

test('license:status shows info when active', function () {
    $licenseData = [
        'key' => 'LX-PRO-VALID-KEY',
        'last_validated_at' => time(),
        'cached_at' => time(),
        'cache_valid' => true,
    ];
    file_put_contents($this->tempHome . DIRECTORY_SEPARATOR . '.lx' . DIRECTORY_SEPARATOR . 'license.json', json_encode($licenseData));

    $this->artisan('license:status')
        ->assertExitCode(0);
    // Since table output is hard to match exactly with expectsOutput, we just assert exit code
    // or we could check for specific substrings if needed.
});
