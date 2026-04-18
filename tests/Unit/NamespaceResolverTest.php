<?php

declare(strict_types=1);

use App\Services\NamespaceResolver;

test('it resolves namespaces from composer psr4 mappings', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot, [
            'App\\' => 'app/',
            'Domain\\' => 'src/Domain/',
        ]);

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
        createTestComposerJson($projectRoot);

        $resolver = new NamespaceResolver;

        expect($resolver->resolve('Modules/Billing/PaymentService.php', $projectRoot))
            ->toBe('App\\Modules\\Billing\\PaymentService');
    } finally {
        deleteDirectory($projectRoot);
    }
});
