<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class NamespaceResolver
{
    public function resolve(string $path, ?string $projectRoot = null): string
    {
        $projectRoot ??= getcwd() ?: '.';

        $composerPath = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerPath)) {
            throw new RuntimeException("Unable to locate composer.json at [{$composerPath}].");
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            throw new RuntimeException("Unable to decode composer.json at [{$composerPath}].");
        }

        $normalizedPath = $this->normalizePath($path);
        $mappings = $this->normalizeMappings($composer['autoload']['psr-4'] ?? []);

        foreach ($mappings as $directory => $namespace) {
            if ($directory !== '' && str_starts_with($normalizedPath, $directory)) {
                $relativePath = trim(substr($normalizedPath, strlen($directory)), '/');

                return $this->qualifyNamespace($namespace, $relativePath);
            }
        }

        return $this->qualifyNamespace('App\\', $normalizedPath);
    }

    /**
     * @param  array<string, string>  $mappings
     * @return array<string, string>
     */
    private function normalizeMappings(array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $namespace => $directory) {
            $normalized[$this->normalizeDirectory($directory)] = $namespace;
        }

        uksort(
            $normalized,
            static fn (string $left, string $right): int => strlen($right) <=> strlen($left)
        );

        return $normalized;
    }

    private function normalizeDirectory(string $directory): string
    {
        $directory = str_replace('\\', '/', trim($directory));

        return trim($directory, '/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('/\.php$/', '', $path) ?? $path;

        return trim($path, '/');
    }

    private function qualifyNamespace(string $namespace, string $relativePath): string
    {
        $relativePath = trim($relativePath, '/');
        $relativeNamespace = str_replace('/', '\\', $relativePath);

        if ($relativeNamespace === '') {
            return rtrim($namespace, '\\');
        }

        return rtrim($namespace, '\\').'\\'.$relativeNamespace;
    }
}
