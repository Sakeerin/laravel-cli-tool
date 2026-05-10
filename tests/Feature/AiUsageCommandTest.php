<?php

declare(strict_types=1);

use App\Services\UsageTracker;
use Mockery\MockInterface;

test('it displays monthly AI usage statistics', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('getMonthlyStats')
            ->with(date('Y-m'))
            ->once()
            ->andReturn(['input_tokens' => 10000, 'output_tokens' => 5000, 'calls' => 25]);

        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:usage')
                ->expectsOutputToContain('10,000')
                ->expectsOutputToContain('5,000')
                ->expectsOutputToContain('25')
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it displays usage for a specific month with --month flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('getMonthlyStats')
            ->with('2026-04')
            ->once()
            ->andReturn(['input_tokens' => 50000, 'output_tokens' => 20000, 'calls' => 100]);

        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:usage', ['--month' => '2026-04'])
                ->expectsOutputToContain('2026-04')
                ->expectsOutputToContain('50,000')
                ->expectsOutputToContain('20,000')
                ->expectsOutputToContain('100')
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it shows zero usage when no calls have been made', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('getMonthlyStats')
            ->with(date('Y-m'))
            ->once()
            ->andReturn(['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0]);

        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:usage')
                ->expectsOutputToContain('0')
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});
