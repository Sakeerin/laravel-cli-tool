<?php

declare(strict_types=1);

namespace App\Commands\Make;

use App\Config\LxConfig;
use App\Services\ScaffoldService;
use LaravelZero\Framework\Commands\Command;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service
                            {name : The service class name}
                            {--interface : Generate a matching interface in app/Contracts}
                            {--test : Generate a Pest unit test in tests/Unit/Services}
                            {--abstract : Generate the service as an abstract class}
                            {--no-constructor : Skip generating a constructor}';

    protected $description = 'Create a new service class';

    public function __construct(
        private readonly ScaffoldService $scaffoldService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $projectRoot = getcwd() ?: '.';
        $config = LxConfig::load($projectRoot);

        $service = $this->buildServiceDefinition($name, $projectRoot, $config);
        $files = [$service];

        if ((bool) $this->option('interface')) {
            $files[] = $this->buildInterfaceDefinition($name, $projectRoot, $config);
        }

        if ((bool) $this->option('test')) {
            $files[] = $this->buildTestDefinition($name, $service['fqcn'], $config);
        }

        foreach ($files as $file) {
            if (is_file($projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']))) {
                $this->components->error("File already exists: {$file['path']}");

                return self::FAILURE;
            }
        }

        foreach ($files as $file) {
            $this->scaffoldService->writeFile($file['path'], $file['contents'], $projectRoot);
            $this->components->info("Created {$file['path']}");
        }

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return trim(str_replace('\\', '/', $name), '/');
    }

    /**
     * @return array{path:string, fqcn:string, contents:string}
     */
    private function buildServiceDefinition(string $name, string $projectRoot, LxConfig $config): array
    {
        $path = "{$config->servicePath()}/{$name}.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);

        [$namespace, $className] = $this->splitClass($fqcn);

        $interfaceFqcn = null;
        $interfaceName = null;

        if ((bool) $this->option('interface')) {
            $interfaceFqcn = $this->scaffoldService->resolveNamespace(
                "{$config->contractPath()}/{$name}Interface.php",
                $projectRoot,
            );
            [, $interfaceName] = $this->splitClass($interfaceFqcn);
        }

        return [
            'path' => $path,
            'fqcn' => $fqcn,
            'contents' => $this->scaffoldService->renderStub('service.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
                'interface_namespace' => $interfaceFqcn,
                'interface_name' => $interfaceName,
                'is_abstract' => (bool) $this->option('abstract'),
                'with_constructor' => ! (bool) $this->option('no-constructor'),
            ]),
        ];
    }

    /**
     * @return array{path:string, fqcn:string, contents:string}
     */
    private function buildInterfaceDefinition(string $name, string $projectRoot, LxConfig $config): array
    {
        $path = "{$config->contractPath()}/{$name}Interface.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);

        [$namespace, $className] = $this->splitClass($fqcn);

        return [
            'path' => $path,
            'fqcn' => $fqcn,
            'contents' => $this->scaffoldService->renderStub('interface.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
            ]),
        ];
    }

    /**
     * @return array{path:string, fqcn:string, contents:string}
     */
    private function buildTestDefinition(string $name, string $serviceFqcn, LxConfig $config): array
    {
        $path = "{$config->testPath()}/Services/{$name}Test.php";
        [, $serviceClass] = $this->splitClass($serviceFqcn);

        return [
            'path' => $path,
            'fqcn' => $path,
            'contents' => $this->scaffoldService->renderStub('test.php.twig', [
                'subject_namespace' => $serviceFqcn,
                'subject_class' => $serviceClass,
                'description' => "it defines the {$serviceClass} service",
                'is_abstract' => (bool) $this->option('abstract'),
            ]),
        ];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitClass(string $fqcn): array
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);

        return [
            implode('\\', $parts),
            $className ?? '',
        ];
    }
}
