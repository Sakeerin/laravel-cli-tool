<?php

declare(strict_types=1);

use App\Services\NamespaceResolver;
use App\Services\ScaffoldService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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

function scaffoldService(): ScaffoldService
{
    $twig = new Environment(
        new FilesystemLoader(app_path('Stubs')),
        [
            'autoescape' => false,
            'cache' => false,
            'strict_variables' => true,
            'trim_blocks' => true,
            'lstrip_blocks' => true,
        ]
    );

    return new ScaffoldService($twig, new NamespaceResolver);
}

if (! function_exists('testProjectRoot')) {
    function testProjectRoot(): string
    {
        $projectRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lx-tests-'.bin2hex(random_bytes(6));

        mkdir($projectRoot, 0777, true);

        return $projectRoot;
    }
}

if (! function_exists('deleteDirectory')) {
    function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (in_array($entry, ['.', '..'], true)) {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($path)) {
                deleteDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
