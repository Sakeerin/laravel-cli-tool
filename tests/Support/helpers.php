<?php

declare(strict_types=1);

use App\Services\NamespaceResolver;
use App\Services\ScaffoldService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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

if (! function_exists('withWorkingDirectory')) {
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    function withWorkingDirectory(string $directory, callable $callback): mixed
    {
        $originalDirectory = getcwd() ?: '.';

        chdir($directory);

        try {
            return $callback();
        } finally {
            chdir($originalDirectory);
        }
    }
}

if (! function_exists('createTestComposerJson')) {
    /**
     * @param  array<string, string>  $psr4
     */
    function createTestComposerJson(string $projectRoot, array $psr4 = ['App\\' => 'app/']): void
    {
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => $psr4,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

if (! function_exists('scaffoldService')) {
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
}
