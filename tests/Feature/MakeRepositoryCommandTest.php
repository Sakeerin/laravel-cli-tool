<?php

declare(strict_types=1);

dataset('make repository options', [
    'default repository' => [
        [],
        function (string $projectRoot): void {
            $repositoryPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepository.php';

            expect(file_get_contents($repositoryPath))
                ->toContain('class UserRepository')
                ->toContain('public function __construct()')
                ->not->toContain('implements UserRepositoryInterface');
        },
    ],
    'repository with model' => [
        ['--model' => 'User'],
        function (string $projectRoot): void {
            $repositoryPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepository.php';

            expect(file_get_contents($repositoryPath))
                ->toContain('use App\Models\User;')
                ->toContain('private readonly User $model');
        },
    ],
    'repository with interface and test' => [
        ['--interface' => true, '--test' => true],
        function (string $projectRoot): void {
            $repositoryPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepository.php';
            $interfacePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR.'UserRepositoryInterface.php';
            $testPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepositoryTest.php';

            expect(file_get_contents($repositoryPath))
                ->toContain('implements UserRepositoryInterface');
            expect(file_get_contents($interfacePath))
                ->toContain('interface UserRepositoryInterface');
            expect(file_get_contents($testPath))
                ->toContain('use App\Repositories\UserRepository;')
                ->toContain("test('it defines the UserRepository repository'");
        },
    ],
    'full option combination' => [
        ['--model' => 'User', '--interface' => true, '--test' => true],
        function (string $projectRoot): void {
            $repositoryPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepository.php';
            $interfacePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR.'UserRepositoryInterface.php';
            $testPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.'UserRepositoryTest.php';

            expect(file_get_contents($repositoryPath))
                ->toContain('use App\Models\User;')
                ->toContain('use App\Contracts\UserRepositoryInterface;')
                ->toContain('class UserRepository implements UserRepositoryInterface')
                ->toContain('private readonly User $model');
            expect(is_file($interfacePath))->toBeTrue();
            expect(is_file($testPath))->toBeTrue();
        },
    ],
]);

test('it generates a repository scaffold for each option combination', function (array $options, Closure $assertions): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function () use ($options): void {
            $this->artisan('make:repository', array_merge(['name' => 'UserRepository'], $options))
                ->expectsOutputToContain('Created app/Repositories/UserRepository.php')
                ->assertExitCode(0);
        });

        $assertions($projectRoot);
    } finally {
        deleteDirectory($projectRoot);
    }
})->with('make repository options');

test('it fails when the repository file already exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $repositoryDirectory = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Repositories';
        mkdir($repositoryDirectory, 0777, true);
        file_put_contents($repositoryDirectory.DIRECTORY_SEPARATOR.'UserRepository.php', '<?php');

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:repository', ['name' => 'UserRepository'])
                ->expectsOutputToContain('File already exists: app/Repositories/UserRepository.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});
