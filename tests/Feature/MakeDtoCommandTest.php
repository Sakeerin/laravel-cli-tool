<?php

declare(strict_types=1);

dataset('make dto options', [
    'default dto' => [
        [],
        function (string $projectRoot): void {
            $dtoPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'CreateUserData.php';

            expect(file_get_contents($dtoPath))
                ->toContain('final class CreateUserData')
                ->not->toContain('readonly')
                ->not->toContain('fromArray');
        },
    ],
    'readonly dto' => [
        ['--readonly' => true],
        function (string $projectRoot): void {
            $dtoPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'CreateUserData.php';

            expect(file_get_contents($dtoPath))
                ->toContain('final readonly class CreateUserData');
        },
    ],
    'dto with from-array' => [
        ['--from-array' => true],
        function (string $projectRoot): void {
            $dtoPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'CreateUserData.php';

            expect(file_get_contents($dtoPath))
                ->toContain('public static function fromArray(array $data): self')
                ->toContain('return new self(');
        },
    ],
    'readonly dto with from-array' => [
        ['--readonly' => true, '--from-array' => true],
        function (string $projectRoot): void {
            $dtoPath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.'CreateUserData.php';

            expect(file_get_contents($dtoPath))
                ->toContain('final readonly class CreateUserData')
                ->toContain('public static function fromArray(array $data): self');
        },
    ],
]);

test('it generates a DTO for each option combination', function (array $options, Closure $assertions): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function () use ($options): void {
            $this->artisan('make:dto', array_merge(['name' => 'CreateUserData'], $options))
                ->expectsOutputToContain('Created app/Data/CreateUserData.php')
                ->assertExitCode(0);
        });

        $assertions($projectRoot);
    } finally {
        deleteDirectory($projectRoot);
    }
})->with('make dto options');

test('it fails when the dto file already exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $dataDirectory = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Data';
        mkdir($dataDirectory, 0777, true);
        file_put_contents($dataDirectory.DIRECTORY_SEPARATOR.'CreateUserData.php', '<?php');

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:dto', ['name' => 'CreateUserData'])
                ->expectsOutputToContain('File already exists: app/Data/CreateUserData.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});
