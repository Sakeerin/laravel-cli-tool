<?php

declare(strict_types=1);

namespace App\Commands\Make;

use App\Config\LxConfig;
use App\Services\ScaffoldService;
use LaravelZero\Framework\Commands\Command;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository
                            {name : The repository class name}
                            {--model= : Bind the repository to an Eloquent model (adds type-hinted constructor injection)}
                            {--interface : Generate a matching interface in app/Contracts}
                            {--test : Generate a Pest unit test in tests/Unit/Repositories}';

    protected $description = 'Create a new repository class';

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

        $repository = $this->buildRepositoryDefinition($name, $projectRoot, $config);
        $files = [$repository];

        if ((bool) $this->option('interface')) {
            $files[] = $this->buildInterfaceDefinition($name, $projectRoot, $config);
        }

        if ((bool) $this->option('test')) {
            $files[] = $this->buildTestDefinition($name, $repository['fqcn'], $config);
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
    private function buildRepositoryDefinition(string $name, string $projectRoot, LxConfig $config): array
    {
        $path = "{$config->repositoryPath()}/{$name}.php";
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

        $modelFqcn = null;
        $modelName = null;
        $modelOption = $this->option('model');

        if ($modelOption) {
            $modelPath = "app/Models/{$modelOption}.php";
            $modelFqcn = $this->scaffoldService->resolveNamespace($modelPath, $projectRoot);
            [, $modelName] = $this->splitClass($modelFqcn);
        }

        return [
            'path' => $path,
            'fqcn' => $fqcn,
            'contents' => $this->scaffoldService->renderStub('repository.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
                'interface_namespace' => $interfaceFqcn,
                'interface_name' => $interfaceName,
                'model_namespace' => $modelFqcn,
                'model_name' => $modelName,
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
    private function buildTestDefinition(string $name, string $repositoryFqcn, LxConfig $config): array
    {
        $path = "{$config->testPath()}/Repositories/{$name}Test.php";
        [, $repositoryClass] = $this->splitClass($repositoryFqcn);

        return [
            'path' => $path,
            'fqcn' => $path,
            'contents' => $this->scaffoldService->renderStub('test.php.twig', [
                'subject_namespace' => $repositoryFqcn,
                'subject_class' => $repositoryClass,
                'description' => "it defines the {$repositoryClass} repository",
                'is_abstract' => false,
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
