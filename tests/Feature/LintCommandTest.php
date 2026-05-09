<?php

declare(strict_types=1);

test('it emits github annotations for lint issues', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        createPhpFile($projectRoot, 'app/Services/Payment.php', <<<'PHP'
<?php

namespace App\Services;

class Payment
{
    public function handle()
    {
        return 'ok';
    }
}
PHP);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('lint', ['paths' => ['app'], '--format' => 'github', '--strict' => true])
                ->expectsOutputToContain('::error file=app/Services/Payment.php')
                ->assertExitCode(1);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it respects ignore patterns from lx config', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        createTestLxConfig($projectRoot, [
            'lint' => [
                'ignore' => ['app/Legacy'],
            ],
        ]);
        createPhpFile($projectRoot, 'app/Legacy/LegacyService.php', <<<'PHP'
<?php

namespace App\Legacy;

class LegacyService
{
    public function handle()
    {
        return 'legacy';
    }
}
PHP);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('lint', ['paths' => ['app'], '--format' => 'json'])
                ->expectsOutputToContain('"issues": []')
                ->assertExitCode(0);
        });
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it auto fixes phpcs issues when requested', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        createPhpFile($projectRoot, 'app/Services/BillingService.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class BillingService{
    public function handle(): void{
    }
}
PHP);

        withWorkingDirectory($projectRoot, function (): void {
            $this->artisan('lint', ['paths' => ['app/Services/BillingService.php'], '--fix' => true])
                ->expectsOutputToContain('Running PHP_CodeSniffer auto-fix...')
                ->assertExitCode(0);
        });

        $contents = file_get_contents($projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'BillingService.php');

        expect($contents)->toContain("class BillingService\n{")
            ->and($contents)->toContain("public function handle(): void\n    {");
    } finally {
        deleteDirectory($projectRoot);
    }
});
