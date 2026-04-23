<?php

declare(strict_types=1);

namespace App\Commands\Make;

use App\Services\ScaffoldService;
use LaravelZero\Framework\Commands\Command;

class MakeDtoCommand extends Command
{
    protected $signature = 'make:dto
                            {name : The DTO class name}
                            {--readonly : Generate the DTO as a readonly class (PHP 8.2+)}
                            {--from-array : Add a static fromArray() factory method}';

    protected $description = 'Create a new DTO (Data Transfer Object) class';

    public function __construct(
        private readonly ScaffoldService $scaffoldService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->normalizeName((string) $this->argument('name'));
        $projectRoot = getcwd() ?: '.';

        $dto = $this->buildDtoDefinition($name, $projectRoot);

        if (is_file($projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dto['path']))) {
            $this->components->error("File already exists: {$dto['path']}");

            return self::FAILURE;
        }

        $this->scaffoldService->writeFile($dto['path'], $dto['contents'], $projectRoot);
        $this->components->info("Created {$dto['path']}");

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return trim(str_replace('\\', '/', $name), '/');
    }

    /**
     * @return array{path:string, fqcn:string, contents:string}
     */
    private function buildDtoDefinition(string $name, string $projectRoot): array
    {
        $path = "app/Data/{$name}.php";
        $fqcn = $this->scaffoldService->resolveNamespace($path, $projectRoot);

        [$namespace, $className] = $this->splitClass($fqcn);

        return [
            'path' => $path,
            'fqcn' => $fqcn,
            'contents' => $this->scaffoldService->renderStub('dto.php.twig', [
                'namespace' => $namespace,
                'class_name' => $className,
                'is_readonly' => (bool) $this->option('readonly'),
                'with_from_array' => (bool) $this->option('from-array'),
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
