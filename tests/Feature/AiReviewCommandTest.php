<?php

declare(strict_types=1);

use App\Services\ClaudeService;
use App\Services\CommandExecutor;
use App\Services\UsageTracker;
use Mockery\MockInterface;

test('it reviews staged changes with --staged flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $diff = "diff --git a/app/Http/Controllers/UserController.php b/app/Http/Controllers/UserController.php\n+    public function index() { return User::all(); }";

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->with(['git', 'diff', '--cached'], Mockery::any())
            ->once()
            ->andReturn(['exit_code' => 0, 'output' => $diff, 'error_output' => '']);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('streamText')->once()->andReturn('⚠ Potential N+1 query found. Consider eager loading.');
        $claude->shouldReceive('getLastInputTokens')->andReturn(300);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(150);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(CommandExecutor::class, $executor);
        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:review', ['--staged' => true])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reviews uncommitted changes with --diff flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $diff = "diff --git a/app/Services/UserService.php b/app/Services/UserService.php\n+    public function getAll() { return User::all(); }";

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->with(['git', 'diff'], Mockery::any())
            ->once()
            ->andReturn(['exit_code' => 0, 'output' => $diff, 'error_output' => '']);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('streamText')->once()->andReturn('No issues found.');
        $claude->shouldReceive('getLastInputTokens')->andReturn(200);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(50);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(CommandExecutor::class, $executor);
        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:review', ['--diff' => true])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reviews a specific file with --file flag', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $controllerDir = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers';
        mkdir($controllerDir, 0777, true);
        file_put_contents(
            $controllerDir.DIRECTORY_SEPARATOR.'FooController.php',
            "<?php\nclass FooController {\n    public function index() { return User::all(); }\n}\n"
        );

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);
        $claude->shouldReceive('streamText')->once()->andReturn('Code review complete: 1 warning found.');
        $claude->shouldReceive('getLastInputTokens')->andReturn(180);
        $claude->shouldReceive('getLastOutputTokens')->andReturn(90);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);
        $tracker->shouldReceive('checkRateLimit')->once();
        $tracker->shouldReceive('recordUsage')->once();

        $this->app->instance(CommandExecutor::class, $executor);
        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:review', ['--file' => 'app/Http/Controllers/FooController.php'])
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it fails when no review option is provided', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);

        $this->app->instance(CommandExecutor::class, $executor);
        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:review')
                ->expectsOutputToContain('Please specify')
                ->assertExitCode(1);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it reports no changes when diff is empty', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        /** @var MockInterface&CommandExecutor $executor */
        $executor = Mockery::mock(CommandExecutor::class);
        $executor->shouldReceive('execute')
            ->with(['git', 'diff', '--cached'], Mockery::any())
            ->once()
            ->andReturn(['exit_code' => 0, 'output' => '', 'error_output' => '']);

        /** @var MockInterface&ClaudeService $claude */
        $claude = Mockery::mock(ClaudeService::class);

        /** @var MockInterface&UsageTracker $tracker */
        $tracker = Mockery::mock(UsageTracker::class);

        $this->app->instance(CommandExecutor::class, $executor);
        $this->app->instance(ClaudeService::class, $claude);
        $this->app->instance(UsageTracker::class, $tracker);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('ai:review', ['--staged' => true])
                ->expectsOutputToContain('No changes to review')
                ->assertExitCode(0);
        });

    } finally {
        deleteDirectory($projectRoot);
    }
});
