<?php

declare(strict_types=1);

use App\Services\NamespaceResolver;

test('it resolves namespaces from composer psr4 mappings', function (): void {
    $projectRoot = testProjectRoot();

    try {
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'App\\' => 'app/',
                        'Domain\\' => 'src/Domain/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $resolver = new NamespaceResolver;

        expect($resolver->resolve('src/Domain/Billing/PaymentService.php', $projectRoot))
            ->toBe('Domain\\Billing\\PaymentService');
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it falls back to the app namespace when no mapping matches', function (): void {
    $projectRoot = testProjectRoot();

    try {
        file_put_contents(
            $projectRoot.DIRECTORY_SEPARATOR.'composer.json',
            json_encode([
                'autoload' => [
                    'psr-4' => [
                        'App\\' => 'app/',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $resolver = new NamespaceResolver;

        expect($resolver->resolve('Modules/Billing/PaymentService.php', $projectRoot))
            ->toBe('App\\Modules\\Billing\\PaymentService');
    } finally {
        deleteDirectory($projectRoot);
    }
});

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
