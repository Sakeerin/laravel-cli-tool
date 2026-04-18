<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Twig\Environment;

class ScaffoldService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly NamespaceResolver $namespaceResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function renderStub(string $template, array $context = []): string
    {
        $rendered = $this->twig->render($template, $context);

        return $this->formatPhp($rendered);
    }

    public function writeFile(string $path, string $contents, ?string $projectRoot = null): string
    {
        $projectRoot ??= getcwd() ?: '.';

        $targetPath = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $directory = dirname($targetPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        if (file_put_contents($targetPath, $contents) === false) {
            throw new RuntimeException("Unable to write file [{$targetPath}].");
        }

        return $targetPath;
    }

    public function resolveNamespace(string $path, ?string $projectRoot = null): string
    {
        return $this->namespaceResolver->resolve($path, $projectRoot);
    }

    private function formatPhp(string $code): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'lx-stub-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary file for PHP-CS-Fixer.');
        }

        $temporaryPhpFile = $temporaryPath.'.php';

        if (! rename($temporaryPath, $temporaryPhpFile)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to prepare a temporary PHP file for PHP-CS-Fixer.');
        }

        try {
            if (file_put_contents($temporaryPhpFile, $code) === false) {
                throw new RuntimeException("Unable to write temporary file [{$temporaryPhpFile}].");
            }

            $process = new Process([
                PHP_BINARY,
                base_path('vendor/bin/php-cs-fixer'),
                'fix',
                $temporaryPhpFile,
                '--quiet',
                '--using-cache=no',
            ]);

            $process->mustRun();

            $formatted = file_get_contents($temporaryPhpFile);

            if ($formatted === false) {
                throw new RuntimeException("Unable to read formatted file [{$temporaryPhpFile}].");
            }

            return rtrim($formatted).PHP_EOL;
        } finally {
            @unlink($temporaryPhpFile);
        }
    }
}
