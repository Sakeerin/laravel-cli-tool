<?php

declare(strict_types=1);

use App\Services\ClaudeService;
use App\Services\UsageTracker;
use Mockery\MockInterface;

test('it analyzes an error message and streams a response', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('streamText')->once()->andReturnUsing(
            function (string $system, string $prompt, callable $onChunk): string {
                $text = "The column does not exist. Run: php artisan migrate";
                $onChunk($text);

                return $text;
            }
        );
        $claude->shouldReceive('getLastInputTokens')->andReturn(50);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(30);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:fix', ['error_message' => 'SQLSTATE[42S22]: Unknown column "paid_amount"'])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reads the last error from laravel.log with --last flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $logDir = $projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        mkdir($logDir, 0777, true);
        file_put_contents(
            $logDir.DIRECTORY_SEPARATOR.'laravel.log',
            "[2026-05-01 10:00:00] local.ERROR: Something went wrong {\"exception\":\"RuntimeException\"}\n"
        );

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('streamText')->once()->andReturn('Analysis: the exception was thrown because...');
        $claude->shouldReceive('getLastInputTokens')->andReturn(80);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(40);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:fix', ['--last' => true])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when no error message is provided and no --last flag', function (): void {
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
            $this->artisan('ai:fix')
                ->expectsOutputToContain('No error message provided')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when --last is used but laravel.log does not exist', function (): void {
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
            $this->artisan('ai:fix', ['--last' => true])
                ->expectsOutputToContain('laravel.log')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});
