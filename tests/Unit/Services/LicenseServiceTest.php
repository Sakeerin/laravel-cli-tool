<?php

use App\Services\LicenseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lx_test_' . uniqid();
    mkdir($this->tempHome . DIRECTORY_SEPARATOR . '.lx', 0755, true);
    $this->licenseService = new LicenseService('https://api.lx.dev', $this->tempHome);
});

afterEach(function () {
    File::deleteDirectory($this->tempHome);
});

test('it returns false when no license file exists', function () {
    expect($this->licenseService->isProActive())->toBeFalse();
});

test('it activates a valid license key', function () {
    Http::fake([
        'api.lx.dev/license/validate' => Http::response(['valid' => true], 200),
    ]);

    $result = $this->licenseService->activate('LX-PRO-VALID-KEY');

    expect($result)->toBeTrue();
    expect(File::exists($this->tempHome . '/.lx/license.json'))->toBeTrue();
    
    $data = json_decode(file_get_contents($this->tempHome . '/.lx/license.json'), true);
    expect($data['key'])->toBe('LX-PRO-VALID-KEY');
    expect($data['cache_valid'])->toBeTrue();
});

test('it fails to activate an invalid license key', function () {
    Http::fake([
        'api.lx.dev/license/validate' => Http::response(['valid' => false], 200),
    ]);

    $result = $this->licenseService->activate('LX-PRO-INVALID-KEY');

    expect($result)->toBeFalse();
    expect(File::exists($this->tempHome . '/.lx/license.json'))->toBeFalse();
});

test('it uses cache if still valid', function () {
    $licenseData = [
        'key' => 'LX-PRO-VALID-KEY',
        'last_validated_at' => time(),
        'cached_at' => time(),
        'cache_valid' => true,
    ];
    file_put_contents($this->tempHome . '/.lx/license.json', json_encode($licenseData));

    Http::fake();

    expect($this->licenseService->isProActive())->toBeTrue();
    Http::assertNothingSent();
});

test('it re-validates if cache is expired', function () {
    $licenseData = [
        'key' => 'LX-PRO-VALID-KEY',
        'last_validated_at' => time() - 90000, // > 24h
        'cached_at' => time() - 90000,
        'cache_valid' => true,
    ];
    file_put_contents($this->tempHome . '/.lx/license.json', json_encode($licenseData));

    Http::fake([
        'api.lx.dev/license/validate' => Http::response(['valid' => true], 200),
    ]);

    expect($this->licenseService->isProActive())->toBeTrue();
    Http::assertSentCount(1);
});

test('it uses grace period if server is unreachable', function () {
    $licenseData = [
        'key' => 'LX-PRO-VALID-KEY',
        'last_validated_at' => time() - 90000, // Cache expired but last validated is recent
        'cached_at' => time() - 90000,
        'cache_valid' => true,
    ];
    file_put_contents($this->tempHome . '/.lx/license.json', json_encode($licenseData));

    Http::fake([
        'api.lx.dev/license/validate' => Http::response([], 500),
    ]);

    expect($this->licenseService->isProActive())->toBeTrue();
});

test('it fails after grace period if server is unreachable', function () {
    $licenseData = [
        'key' => 'LX-PRO-VALID-KEY',
        'last_validated_at' => time() - (8 * 86400), // > 7 days
        'cached_at' => time() - 90000,
        'cache_valid' => true,
    ];
    file_put_contents($this->tempHome . '/.lx/license.json', json_encode($licenseData));

    Http::fake([
        'api.lx.dev/license/validate' => Http::response([], 500),
    ]);

    expect($this->licenseService->isProActive())->toBeFalse();
});

test('requirePro throws exception if not active', function () {
    expect(fn() => $this->licenseService->requirePro())->toThrow(\RuntimeException::class);
});
