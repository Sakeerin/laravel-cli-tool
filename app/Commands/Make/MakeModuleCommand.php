<?php

declare(strict_types=1);

namespace App\Commands\Make;

use App\Config\LxConfig;
use App\Services\ScaffoldService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
                            {name : The module name (e.g. Billing, UserProfile)}
                            {--model : Generate an Eloquent Model}
                            {--controller : Generate a Resource Controller}
                            {--service : Generate a Service + Interface}
                            {--repository : Generate a Repository + Interface}
                            {--policy : Generate a Policy}
                            {--migration : Generate a Migration}
                            {--seeder : Generate a Seeder}
                            {--test : Generate Feature + Unit Tests}
                            {--all : Generate all of the above}';

    protected $description = 'Scaffold a complete module (Model, Controller, Service, Repository, Policy, Migration, ...)';

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
        $useAll = (bool) $this->option('all');

        $files = [];

        if ($useAll || (bool) $this->option('model')) {
            $files = array_merge($files, $this->buildModelFiles($name, $projectRoot));
        }

        if ($useAll || (bool) $this->option('controller')) {
            $files = array_merge($files, $this->buildControllerFiles($name, $projectRoot));
        }

        if ($useAll || (bool) $this->option('service')) {
            $files = array_merge($files, $this->buildServiceFiles($name, $projectRoot, $config));
        }

        if ($useAll || (bool) $this->option('repository')) {
            $files = array_merge($files, $this->buildRepositoryFiles($name, $projectRoot, $config));
        }

        if ($useAll || (bool) $this->option('policy')) {
            $files = array_merge($files, $this->buildPolicyFiles($name, $projectRoot));
        }

        if ($useAll || (bool) $this->option('migration')) {
            $files = array_merge($files, $this->buildMigrationFiles($name));
        }

        if ($useAll || (bool) $this->option('seeder')) {
            $files = array_merge($files, $this->buildSeederFiles($name, $projectRoot));
        }

        if ($useAll || (bool) $this->option('test')) {
            $files = array_merge($files, $this->buildTestFiles($name, $projectRoot, $config));
        }

        if ($useAll || (bool) $this->option('all')) {
            $files = array_merge($files, $this->buildRouteFiles($name, $projectRoot));
        }

        if ($files === []) {
            $this->components->warn('No files to generate. Use --all or specify individual options (--model, --controller, etc.)');

            return self::SUCCESS;
        }

        // Check for conflicts before writing anything
        foreach ($files as $file) {
            if (is_file($projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']))) {
                $this->components->error("File already exists: {$file['path']}");

                return self::FAILURE;
            }
        }

        // Transaction-like write: rollback on failure
        $written = [];

        try {
            foreach ($files as $file) {
                $this->scaffoldService->writeFile($file['path'], $file['contents'], $projectRoot);
                $written[] = $file['path'];
                $this->components->info("Created {$file['path']}");
            }
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                $fullPath = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }

            $this->components->error("Module generation failed — rolled back: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return trim(str_replace('\\', '/', $name), '/');
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildModelFiles(string $name, string $projectRoot): array
    {
        $path = "app/Models/{$name}.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);
        [$namespace, $className] = $this->splitClass($fqcn);

        return [[
            'path' => $path,
            'contents' => $this->scaffoldService->renderStub('module/model.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
            ]),
        ]];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildControllerFiles(string $name, string $projectRoot): array
    {
        $path = "app/Http/Controllers/{$name}Controller.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);
        [$namespace, $className] = $this->splitClass($fqcn);

        return [[
            'path' => $path,
            'contents' => $this->scaffoldService->renderStub('module/controller.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
            ]),
        ]];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildServiceFiles(string $name, string $projectRoot, LxConfig $config): array
    {
        $servicePath = "{$config->servicePath()}/{$name}Service.php";
        $serviceFqcn = $this->scaffoldService->resolveNamespace($servicePath, $projectRoot);
        [$serviceNamespace, $serviceClass] = $this->splitClass($serviceFqcn);

        $interfacePath = "{$config->contractPath()}/{$name}ServiceInterface.php";
        $interfaceFqcn = $this->scaffoldService->resolveNamespace($interfacePath, $projectRoot);
        [$interfaceNamespace, $interfaceClass] = $this->splitClass($interfaceFqcn);

        return [
            [
                'path' => $servicePath,
                'contents' => $this->scaffoldService->renderStub('service.php.twig', [
                    'namespace' => $serviceNamespace,
                    'class_name' => $serviceClass,
                    'interface_namespace' => $interfaceFqcn,
                    'interface_name' => $interfaceClass,
                    'is_abstract' => false,
                    'with_constructor' => true,
                ]),
            ],
            [
                'path' => $interfacePath,
                'contents' => $this->scaffoldService->renderStub('interface.php.twig', [
                    'namespace' => $interfaceNamespace,
                    'class_name' => $interfaceClass,
                ]),
            ],
        ];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildRepositoryFiles(string $name, string $projectRoot, LxConfig $config): array
    {
        $repoPath = "{$config->repositoryPath()}/{$name}Repository.php";
        $repoFqcn = $this->scaffoldService->resolveNamespace($repoPath, $projectRoot);
        [$repoNamespace, $repoClass] = $this->splitClass($repoFqcn);

        $interfacePath = "{$config->contractPath()}/{$name}RepositoryInterface.php";
        $interfaceFqcn = $this->scaffoldService->resolveNamespace($interfacePath, $projectRoot);
        [$interfaceNamespace, $interfaceClass] = $this->splitClass($interfaceFqcn);

        $modelPath = "app/Models/{$name}.php";
        $modelFqcn = $this->scaffoldService->resolveNamespace($modelPath, $projectRoot);
        [, $modelClass] = $this->splitClass($modelFqcn);

        return [
            [
                'path' => $repoPath,
                'contents' => $this->scaffoldService->renderStub('repository.php.twig', [
                    'namespace' => $repoNamespace,
                    'class_name' => $repoClass,
                    'interface_namespace' => $interfaceFqcn,
                    'interface_name' => $interfaceClass,
                    'model_namespace' => $modelFqcn,
                    'model_name' => $modelClass,
                ]),
            ],
            [
                'path' => $interfacePath,
                'contents' => $this->scaffoldService->renderStub('interface.php.twig', [
                    'namespace' => $interfaceNamespace,
                    'class_name' => $interfaceClass,
                ]),
            ],
        ];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildPolicyFiles(string $name, string $projectRoot): array
    {
        $path = "app/Policies/{$name}Policy.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);
        [$namespace, $className] = $this->splitClass($fqcn);

        return [[
            'path' => $path,
            'contents' => $this->scaffoldService->renderStub('module/policy.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
            ]),
        ]];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildMigrationFiles(string $name): array
    {
        $tableName = Str::snake(Str::plural($name));
        $timestamp = date('Y_m_d_His');
        $path = "database/migrations/{$timestamp}_create_{$tableName}_table.php";

        return [[
            'path' => $path,
            'contents' => $this->scaffoldService->renderStub('module/migration.php.twig', [
                'table_name' => $tableName,
            ]),
        ]];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildSeederFiles(string $name, string $projectRoot): array
    {
        $path = "database/seeders/{$name}Seeder.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);
        [$namespace, $className] = $this->splitClass($fqcn);

        return [[
            'path' => $path,
            'contents' => $this->scaffoldService->renderStub('module/seeder.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
            ]),
        ]];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildTestFiles(string $name, string $projectRoot, LxConfig $config): array
    {
        $controllerPath = "app/Http/Controllers/{$name}Controller.php";
        $controllerFqcn = $this->scaffoldService->resolveNamespace($controllerPath, $projectRoot);
        [, $controllerClass] = $this->splitClass($controllerFqcn);

        $servicePath = "{$config->servicePath()}/{$name}Service.php";
        $serviceFqcn = $this->scaffoldService->resolveNamespace($servicePath, $projectRoot);
        [, $serviceClass] = $this->splitClass($serviceFqcn);

        return [
            [
                'path' => "tests/Feature/{$name}ControllerTest.php",
                'contents' => $this->scaffoldService->renderStub('module/feature-test.php.twig', [
                    'controller_namespace' => $controllerFqcn,
                    'controller_class' => $controllerClass,
                ]),
            ],
            [
                'path' => "{$config->testPath()}/Services/{$name}ServiceTest.php",
                'contents' => $this->scaffoldService->renderStub('test.php.twig', [
                    'subject_namespace' => $serviceFqcn,
                    'subject_class' => $serviceClass,
                    'description' => "it defines the {$serviceClass} service",
                    'is_abstract' => false,
                ]),
            ],
        ];
    }

    /**
     * @return list<array{path: string, contents: string}>
     */
    private function buildRouteFiles(string $name, string $projectRoot): array
    {
        $controllerPath = "app/Http/Controllers/{$name}Controller.php";
        $controllerFqcn = $this->scaffoldService->resolveNamespace($controllerPath, $projectRoot);
        [, $controllerClass] = $this->splitClass($controllerFqcn);

        $routeName = Str::snake($name);

        return [[
            'path' => "routes/{$routeName}.php",
            'contents' => $this->scaffoldService->renderStub('module/routes.php.twig', [
                'controller_namespace' => $controllerFqcn,
                'controller_class' => $controllerClass,
                'route_name' => $routeName,
            ]),
        ]];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitClass(string $fqcn): array
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);

        return [implode('\\', $parts), $className ?? ''];
    }
}
