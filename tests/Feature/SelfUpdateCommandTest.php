<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

test('self-update shows already latest version', function () {
    Config::set('app.version', '1.0.0');

    Http::fake([
        'api.github.com/repos/Sakeerin/laravel-cli-tool/releases/latest' => Http::response([
            'tag_name' => 'v1.0.0',
        ], 200),
    ]);

    $this->artisan('self-update')
        ->expectsOutput('Current version: 1.0.0')
        ->expectsOutput('You are already using the latest version.')
        ->assertExitCode(0);
});

test('self-update identifies new version but user declines', function () {
    Config::set('app.version', '1.0.0');

    Http::fake([
        'api.github.com/repos/Sakeerin/laravel-cli-tool/releases/latest' => Http::response([
            'tag_name' => 'v1.1.0',
            'assets' => [
                ['name' => 'lx.phar', 'browser_download_url' => 'https://example.com/lx.phar']
            ]
        ], 200),
    ]);

    $this->artisan('self-update')
        ->expectsOutput('Current version: 1.0.0')
        ->expectsOutput('New version available: 1.1.0')
        ->expectsConfirmation('Do you want to update?', 'no')
        ->assertExitCode(0);
});
