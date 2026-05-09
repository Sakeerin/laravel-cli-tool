<?php

declare(strict_types=1);

test('it reports missing env file', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        // No .env file

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('check')
                ->expectsOutputToContain('.env file not found')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reports missing required env variables', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:xxx\n");

        createTestLxConfig($projectRoot, [
            'check' => ['required_env' => ['APP_KEY', 'DB_CONNECTION']],
        ]);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('check')
                ->expectsOutputToContain('DB_CONNECTION')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reports missing app key in configuration section', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_DEBUG=false\n");

        createTestLxConfig($projectRoot, ['check' => ['required_env' => [], 'warn_if' => []]]);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('check')
                ->expectsOutputToContain('APP_KEY is not set')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it shows fix suggestions when --fix flag is used', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_DEBUG=false\n");

        createTestLxConfig($projectRoot, ['check' => ['required_env' => [], 'warn_if' => []]]);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('check', ['--fix' => true])
                ->expectsOutputToContain('Suggested fixes')
                ->expectsOutputToContain('php artisan key:generate')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it passes with a healthy project', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        // All four default required_env keys + non-warning values for warn_if rules
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'.env',
            "APP_KEY=base64:xxx\nDB_CONNECTION=mysql\nQUEUE_CONNECTION=redis\nCACHE_DRIVER=file\nAPP_DEBUG=false\n"
        );
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'.env.example',
            "APP_KEY=\nDB_CONNECTION=\nQUEUE_CONNECTION=\nCACHE_DRIVER=\nAPP_DEBUG=false\n"
        );
        // No custom LxConfig: defaults require the four keys above (all present)
        // APP_DEBUG=false and QUEUE_CONNECTION=redis won't trigger any default warn_if rules

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('check')
                ->expectsOutputToContain('APP_KEY is set')
                ->assertExitCode(0);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});
