<?php

declare(strict_types=1);

use App\Services\ClaudeService;
use App\Services\UsageTracker;
use Mockery\MockInterface;

function validPestTest(): string
{
    return "<?php\ndeclare(strict_types=1);\n\ntest('it adds numbers correctly', function (): void {\n    expect(1 + 2)->toBe(3);\n});";
}

test('it generates a Pest test file from a PHP source file', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $serviceDir = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
        mkdir($serviceDir, 0777, true);
        file_put_contents(
            $serviceDir.DIRECTORY_SEPARATOR.'CalculatorService.php',
            "<?php\nnamespace App\\Services;\nclass CalculatorService {\n    public function add(int \$a, int \$b): int { return \$a + \$b; }\n}\n"
        );

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')->once()->andReturn(validPestTest());
        $claude->shouldReceive('getLastInputTokens')->andReturn(200);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(100);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:test', ['file' => 'app/Services/CalculatorService.php'])
                ->expectsOutputToContain('Created tests/Unit/Services/CalculatorServiceTest.php')
                ->assertExitCode(0);
        });

        $testFile = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'CalculatorServiceTest.php';
        expect(is_file($testFile))->toBeTrue()
            ->and(file_get_contents($testFile))->toContain('it adds numbers correctly');

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it shows the test without saving when --dry-run is used', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $serviceDir = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
        mkdir($serviceDir, 0777, true);
        file_put_contents(
            $serviceDir.DIRECTORY_SEPARATOR.'FooService.php',
            "<?php\nclass FooService { public function doSomething(): void {} }\n"
        );

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')->once()->andReturn(validPestTest());

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:test', ['file' => 'app/Services/FooService.php', '--dry-run' => true])
                ->assertExitCode(0);
        });

        $testFile = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'FooServiceTest.php';
        expect(is_file($testFile))->toBeFalse();

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when the source file does not exist', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:test', ['file' => 'app/Services/NonExistent.php'])
                ->expectsOutputToContain('File not found')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it generates a test targeting a specific method with ::MethodName syntax', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $serviceDir = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
        mkdir($serviceDir, 0777, true);
        file_put_contents(
            $serviceDir.DIRECTORY_SEPARATOR.'MathService.php',
            "<?php\nclass MathService {\n    public function multiply(int \$a, int \$b): int { return \$a * \$b; }\n    public function divide(int \$a, int \$b): float { return \$a / \$b; }\n}\n"
        );

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')
            ->once()
            ->withArgs(function (string $system, string $prompt): bool {
                return str_contains($prompt, 'multiply');
            })
            ->andReturn(validPestTest());
        $claude->shouldReceive('getLastInputTokens')->andReturn(150);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(80);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:test', ['file' => 'app/Services/MathService.php::multiply'])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});
