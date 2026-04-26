<?php

declare(strict_types=1);

dataset('make action options', [
    'default action' => [
        [],
        function (string $projectRoot): void {
            $actionPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR.'CreateUserAction.php';

            expect(file_get_contents($actionPath))
                ->toContain('class CreateUserAction')
                ->toContain('public function handle()')
                ->not->toContain('public function __invoke()');
        },
    ],
    'invokable action' => [
        ['--invokable' => true],
        function (string $projectRoot): void {
            $actionPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR.'CreateUserAction.php';

            expect(file_get_contents($actionPath))
                ->toContain('class CreateUserAction')
                ->toContain('public function __invoke()')
                ->not->toContain('public function handle()');
        },
    ],
    'action with test' => [
        ['--test' => true],
        function (string $projectRoot): void {
            $actionPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR.'CreateUserAction.php';
            $testPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR.'CreateUserActionTest.php';

            expect(is_file($actionPath))->toBeTrue();
            expect(file_get_contents($testPath))
                ->toContain('use App\Actions\CreateUserAction;')
                ->toContain("test('it executes the CreateUserAction action'");
        },
    ],
]);

test('it generates an action scaffold for each option combination', function (array $options, Closure $assertions): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function () use ($options): void {
            $this->artisan('make:action', array_merge(['name' => 'CreateUserAction'], $options))
                ->expectsOutputToContain('Created app/Actions/CreateUserAction.php')
                ->assertExitCode(0);
        });

        $assertions($projectRoot);
    } finally {
        deleteDirectory($projectRoot);
    }
})->with('make action options');

test('it fails when the action file already exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $actionDirectory = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Actions';
        mkdir($actionDirectory, 0777, true);
        file_put_contents($actionDirectory.DIRECTORY_SEPARATOR.'CreateUserAction.php', '<?php');

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:action', ['name' => 'CreateUserAction'])
                ->expectsOutputToContain('File already exists: app/Actions/CreateUserAction.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});
