<?php

declare(strict_types=1);

use App\Config\LxConfig;
use App\Services\CommandExecutor;
use App\Services\HealthChecker;
use Mockery\MockInterface;

function makeChecker(?MockInterface $executor = null): HealthChecker
{
    /** @var MockInterface&CommandExecutor $mock */
    $mock = $executor ?? Mockery::mock(CommandExecutor::class);

    if (! $executor) {
        $mock->shouldReceive('execute')->andReturn([
            'exit_code' => 0,
            'output' => json_encode(['advisories' => []]),
            'error_output' => '',
        ]);
    }

    return new HealthChecker($mock);
}

// ---------------------------------------------------------------------------
// PHP version
// ---------------------------------------------------------------------------

test('it passes php version check when php satisfies composer constraint', function (): void {
    $projectRoot = testProjectRoot();

    try {
        $composerData = [
            'require' => ['php' => '^'.PHP_MAJOR_VERSION.'.0'],
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ];
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'composer.json', json_encode($composerData));

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'PHP version');

        expect($result['status'])->toBe('pass');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails php version check when php does not satisfy constraint', function (): void {
    $projectRoot = testProjectRoot();

    try {
        $composerData = [
            'require' => ['php' => '^4.0'],   // impossible constraint
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ];
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'composer.json', json_encode($composerData));

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'PHP version');

        expect($result['status'])->toBe('fail')
            ->and($result['fix'])->not->toBeNull();
    } finally {
        deleteDirectory($projectRoot);
    }
});

// ---------------------------------------------------------------------------
// .env variables
// ---------------------------------------------------------------------------

test('it fails when .env file is missing', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', '.env file');

        expect($result['status'])->toBe('fail')
            ->and($result['fix'])->toContain('cp .env.example .env');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when required env variables are missing', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:xxx\n");

        createTestLxConfig($projectRoot, [
            'check' => ['required_env' => ['APP_KEY', 'DB_CONNECTION']],
        ]);

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'Required .env variables');

        expect($result['status'])->toBe('fail')
            ->and($result['message'])->toContain('DB_CONNECTION');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it passes when all required env variables are present', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        // Satisfy all four default required_env keys
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'.env',
            "APP_KEY=base64:xxx\nDB_CONNECTION=mysql\nQUEUE_CONNECTION=redis\nCACHE_DRIVER=file\n"
        );

        // No custom LxConfig: use defaults (requires the four keys above)
        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'Required .env variables');

        expect($result['status'])->toBe('pass');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it warns when env variables from env.example are missing in env', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env.example', "APP_KEY=\nDB_CONNECTION=\nQUEUE_CONNECTION=\n");
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:xxx\n");

        createTestLxConfig($projectRoot, ['check' => ['required_env' => []]]);

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', '.env completeness');

        expect($result['status'])->toBe('warn')
            ->and($result['message'])->toContain('DB_CONNECTION');
    } finally {
        deleteDirectory($projectRoot);
    }
});

// ---------------------------------------------------------------------------
// Security advisories
// ---------------------------------------------------------------------------

test('it passes security check when no vulnerabilities found', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->andReturn(['exit_code' => 0, 'output' => json_encode(['advisories' => []]), 'error_output' => '']);

        $results = (new HealthChecker($executor))->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'Security advisories');

        expect($result['status'])->toBe('pass');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails security check when vulnerabilities are found', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->andReturn([
                'exit_code' => 1,
                'output' => json_encode(['advisories' => ['vendor/package' => [['title' => 'CVE-2024-xxx']]]]),
                'error_output' => '',
            ]);

        $results = (new HealthChecker($executor))->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'Security advisories');

        expect($result['status'])->toBe('fail')
            ->and($result['message'])->toContain('vendor/package');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it warns when composer audit cannot be completed', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->andReturn(['exit_code' => 2, 'output' => '', 'error_output' => 'composer: command not found']);

        $results = (new HealthChecker($executor))->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'Security advisories');

        expect($result['status'])->toBe('warn');
    } finally {
        deleteDirectory($projectRoot);
    }
});

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

test('it fails configuration check when app key is missing', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_DEBUG=false\n");

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'APP_KEY');

        expect($result['status'])->toBe('fail')
            ->and($result['fix'])->toContain('key:generate');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it warns on matching warn_if rule', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        file_put_contents($projectRoot.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:xxx\nAPP_DEBUG=true\n");

        createTestLxConfig($projectRoot, [
            'check' => [
                'required_env' => [],
                'warn_if' => [['key' => 'APP_DEBUG', 'value' => 'true', 'message' => 'APP_DEBUG should be false']],
            ],
        ]);

        $results = makeChecker()->run($projectRoot, LxConfig::load($projectRoot));
        $result = collect($results)->firstWhere('label', 'APP_DEBUG');

        expect($result['status'])->toBe('warn')
            ->and($result['message'])->toContain('APP_DEBUG should be false');
    } finally {
        deleteDirectory($projectRoot);
    }
});
