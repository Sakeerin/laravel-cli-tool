<?php

declare(strict_types=1);

use App\Config\LxConfig;

test('it returns defaults when no config file exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        $config = LxConfig::load($projectRoot);

        expect($config->servicePath())->toBe('app/Services')
            ->and($config->repositoryPath())->toBe('app/Repositories')
            ->and($config->dtoPath())->toBe('app/Data')
            ->and($config->actionPath())->toBe('app/Actions')
            ->and($config->testPath())->toBe('tests/Unit')
            ->and($config->get('scaffold.use_readonly_dto'))->toBeTrue();
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it merges project overrides with defaults', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestLxConfig($projectRoot, [
            'scaffold' => [
                'service_path' => 'src/Services',
                'test_path' => 'tests/Architecture',
            ],
            'lint' => [
                'ignore' => ['app/Legacy'],
            ],
        ]);

        $config = LxConfig::load($projectRoot);

        expect($config->servicePath())->toBe('src/Services')
            ->and($config->testPath())->toBe('tests/Architecture')
            ->and($config->contractPath())->toBe('app/Contracts')
            ->and($config->get('lint.ignore'))->toBe(['app/Legacy']);
    } finally {
        deleteDirectory($projectRoot);
    }
});
