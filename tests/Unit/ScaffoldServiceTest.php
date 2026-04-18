<?php

declare(strict_types=1);

test('it renders and formats the service stub', function (): void {
    $service = scaffoldService();

    $rendered = $service->renderStub('service.php.twig', [
        'namespace' => 'App\\Services',
        'class_name' => 'PaymentService',
        'interface_namespace' => 'App\\Contracts\\PaymentServiceInterface',
        'interface_name' => 'PaymentServiceInterface',
        'is_abstract' => false,
        'with_constructor' => true,
    ]);

    expect($rendered)
        ->toContain('declare(strict_types=1);')
        ->toContain('namespace App\Services;')
        ->toContain('use App\Contracts\PaymentServiceInterface;')
        ->toContain('class PaymentService implements PaymentServiceInterface')
        ->toContain('public function __construct()');
});

test('it creates directories when writing a file', function (): void {
    $projectRoot = testProjectRoot();
    $service = scaffoldService();

    try {
        $writtenPath = $service->writeFile(
            'app/Services/GeneratedService.php',
            "<?php\n\ndeclare(strict_types=1);\n",
            $projectRoot,
        );

        expect($writtenPath)->toBe($projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'GeneratedService.php');
        expect(is_file($writtenPath))->toBeTrue();
    } finally {
        deleteDirectory($projectRoot);
    }
});
