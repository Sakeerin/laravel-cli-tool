<?php

use App\Services\LicenseService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lx_ai_guard_test_' . uniqid();
    mkdir($this->tempHome . DIRECTORY_SEPARATOR . '.lx', 0755, true);
    
    $this->app->singleton(LicenseService::class, function () {
        return new LicenseService('https://api.lx.dev', $this->tempHome);
    });
});

afterEach(function () {
    File::deleteDirectory($this->tempHome);
});

test('ai:fix fails without license', function () {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('This command requires lx Pro.');
    
    $this->artisan('ai:fix "Some error"');
});
