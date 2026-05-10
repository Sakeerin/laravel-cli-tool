<?php

declare(strict_types=1);

use App\Services\ClaudeService;
use App\Services\UsageTracker;
use Mockery\MockInterface;

// Valid PHP migration code to return from mock
function validMigrationCode(string $table = 'orders'): string
{
    return "<?php\ndeclare(strict_types=1);\n\nreturn new class {\n    public function up(): void { }\n    public function down(): void { }\n};";
}

test('it generates a migration file from a description', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')->once()->andReturn(validMigrationCode());
        $claude->shouldReceive('getLastInputTokens')->andReturn(100);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(200);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:migration', ['description' => 'orders table with user_id and total'])
                ->expectsOutputToContain('Created database/migrations')
                ->assertExitCode(0);
        });

        $migrationDir = $projectRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $files = glob($migrationDir.DIRECTORY_SEPARATOR.'*.php') ?: [];
        expect($files)->not->toBeEmpty();

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it shows migration without saving when --dry-run is used', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')->once()->andReturn(validMigrationCode('items'));

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:migration', ['description' => 'items table', '--dry-run' => true])
                ->expectsOutputToContain('dry-run')
                ->assertExitCode(0);
        });

        $migrationDir = $projectRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        expect(is_dir($migrationDir))->toBeFalse();

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when the API key is missing', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('complete')->once()->andThrow(
            new \RuntimeException('ANTHROPIC_API_KEY is not set.')
        );

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:migration', ['description' => 'test table'])
                ->expectsOutputToContain('ANTHROPIC_API_KEY')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when rate limit is exceeded', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once()->andThrow(
            new \RuntimeException('Rate limit exceeded: max 10 AI calls per minute.')
        );

        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:migration', ['description' => 'some table'])
                ->expectsOutputToContain('Rate limit exceeded')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});
