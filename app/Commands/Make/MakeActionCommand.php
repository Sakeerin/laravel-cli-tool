<?php

declare(strict_types=1);

namespace App\Commands\Make;

use App\Services\ScaffoldService;
use LaravelZero\Framework\Commands\Command;

class MakeActionCommand extends Command
{
    protected $signature = 'make:action
                            {name : The action class name}
                            {--invokable : Generate a single __invoke() method}
                            {--test : Generate a Pest unit test in tests/Unit/Actions}';

    protected $description = 'Create a new action class';

    public function __construct(
        private readonly ScaffoldService $scaffoldService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $projectRoot = getcwd() ?: '.';

        $action = $this->buildActionDefinition($name, $projectRoot);
        $files = [$action];

        if ((bool) $this->option('test')) {
            $files[] = $this->buildTestDefinition($name, $action['fqcn']);
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
    private function buildActionDefinition(string $name, string $projectRoot): array
    {
        $path = "app/Actions/{$name}.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);

        [$namespace, $className] = $this->splitClass($fqcn);

        return [
            'path' => $path,
            'fqcn' => $fqcn,
            'contents' => $this->scaffoldService->renderStub('action.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
                'is_invokable' => (bool) $this->option('invokable'),
            ]),
        ];
    }

    /**
     * @return array{path:string, fqcn:string, contents:string}
     */
    private function buildTestDefinition(string $name, string $actionFqcn): array
    {
        $path = "tests/Unit/Actions/{$name}Test.php";
        [, $actionClass] = $this->splitClass($actionFqcn);

        return [
            'path' => $path,
            'fqcn' => $path,
            'contents' => $this->scaffoldService->renderStub('test.php.twig', [
                'subject_namespace' => $actionFqcn,
                'subject_class' => $actionClass,
                'description' => "it executes the {$actionClass} action",
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
