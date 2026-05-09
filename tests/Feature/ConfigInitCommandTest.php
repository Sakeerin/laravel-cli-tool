<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('it creates lxconfig yml with wizard answers', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('config:init')
                ->expectsQuestion('Service path', 'app/Services')
                ->expectsQuestion('Repository path', 'app/Repositories')
                ->expectsQuestion('DTO path', 'app/Data')
                ->expectsQuestion('Action path', 'app/Actions')
                ->expectsQuestion('Test path', 'tests/Unit')
                ->expectsConfirmation('Use readonly DTOs by default?', 'yes')
                ->expectsQuestion('Required .env variables (comma-separated)', 'APP_KEY, DB_CONNECTION')
                ->expectsOutputToContain('Created .lxconfig.yml')
                ->assertExitCode(0);
        });

        $configPath = $projectRoot.DIRECTORY_SEPARATOR.'.lxconfig.yml';
        expect(is_file($configPath))->toBeTrue();

        $parsed = Yaml::parseFile($configPath);
        expect($parsed['scaffold']['service_path'])->toBe('app/Services')
            ->and($parsed['scaffold']['use_readonly_dto'])->toBeTrue()
            ->and($parsed['check']['required_env'])->toContain('APP_KEY')
            ->and($parsed['check']['required_env'])->toContain('DB_CONNECTION');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it aborts when user declines to overwrite existing config', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        createTestLxConfig($projectRoot);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('config:init')
                ->expectsConfirmation('.lxconfig.yml already exists. Overwrite?', 'no')
                ->expectsOutputToContain('Aborted')
                ->assertExitCode(0);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});
