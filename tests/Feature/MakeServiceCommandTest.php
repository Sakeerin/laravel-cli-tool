<?php

declare(strict_types=1);

dataset('make service options', [
    'default service' => [
        [],
        function (string $projectRoot): void {
            $servicePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentService.php';

            expect(file_get_contents($servicePath))
                ->toContain('class PaymentService')
                ->toContain('public function __construct()')
                ->not->toContain('implements PaymentServiceInterface');
        },
    ],
    'service with interface and test' => [
        ['--interface' => true, '--test' => true],
        function (string $projectRoot): void {
            $servicePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentService.php';
            $interfacePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR.'PaymentServiceInterface.php';
            $testPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentServiceTest.php';

            expect(file_get_contents($servicePath))
                ->toContain('implements PaymentServiceInterface');
            expect(file_get_contents($interfacePath))
                ->toContain('interface PaymentServiceInterface');
            expect(file_get_contents($testPath))
                ->toContain('use App\Services\PaymentService;')
                ->toContain("test('it defines the PaymentService service'");
        },
    ],
    'abstract service without constructor' => [
        ['--abstract' => true, '--no-constructor' => true, '--test' => true],
        function (string $projectRoot): void {
            $servicePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentService.php';
            $testPath = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentServiceTest.php';

            expect(file_get_contents($servicePath))
                ->toContain('abstract class PaymentService')
                ->not->toContain('public function __construct()');
            expect(file_get_contents($testPath))
                ->toContain('->and($reflection->isAbstract())->toBe(true);');
        },
    ],
    'full option combination' => [
        [
            '--interface' => true,
            '--test' => true,
            '--abstract' => true,
            '--no-constructor' => true,
        ],
        function (string $projectRoot): void {
            $servicePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'PaymentService.php';
            $interfacePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR.'PaymentServiceInterface.php';

            expect(file_get_contents($servicePath))
                ->toContain('abstract class PaymentService implements PaymentServiceInterface')
                ->not->toContain('public function __construct()');
            expect(is_file($interfacePath))->toBeTrue();
        },
    ],
]);

test('it generates a service scaffold for each option combination', function (array $options, Closure $assertions): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        withWorkingDirectory($projectRoot, function () use ($options): void {
            $this->artisan('make:service', array_merge(['name' => 'PaymentService'], $options))
                ->expectsOutputToContain('Created app/Services/PaymentService.php')
                ->assertExitCode(0);
        });

        $assertions($projectRoot);
    } finally {
        deleteDirectory($projectRoot);
    }
})->with('make service options');

test('it fails when the service file already exists', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);

        $serviceDirectory = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
        mkdir($serviceDirectory, 0777, true);
        file_put_contents($serviceDirectory.DIRECTORY_SEPARATOR.'PaymentService.php', '<?php');

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:service', ['name' => 'PaymentService'])
                ->expectsOutputToContain('File already exists: app/Services/PaymentService.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it uses custom scaffold paths from .lxconfig.yml', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot, [
            'App\\' => 'src/',
            'Tests\\' => 'tests/',
        ]);
        createTestLxConfig($projectRoot, [
            'scaffold' => [
                'service_path' => 'src/Domain/Services',
                'contract_path' => 'src/Domain/Contracts',
                'test_path' => 'tests/Architecture',
            ],
        ]);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('make:service', ['name' => 'Billing/PaymentService', '--interface' => true, '--test' => true])
                ->expectsOutputToContain('Created src/Domain/Services/Billing/PaymentService.php')
                ->assertExitCode(0);
        });

        expect(is_file($projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Billing'.DIRECTORY_SEPARATOR.'PaymentService.php'))->toBeTrue();
        expect(is_file($projectRoot.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.'Contracts'.DIRECTORY_SEPARATOR.'Billing'.DIRECTORY_SEPARATOR.'PaymentServiceInterface.php'))->toBeTrue();
        expect(is_file($projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Architecture'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Billing'.DIRECTORY_SEPARATOR.'PaymentServiceTest.php'))->toBeTrue();
    } finally {
        deleteDirectory($projectRoot);
    }
});
