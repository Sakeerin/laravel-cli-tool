<?php

declare(strict_types=1);

use App\Config\LxConfig;
use App\Services\CommandExecutor;
use App\Services\ConventionLinter;
use Mockery\MockInterface;

test('it parses php codesniffer json output into structured issues', function (): void {
    $projectRoot = testProjectRoot();

    try {
        createTestComposerJson($projectRoot);
        $filePath = $projectRoot.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
        mkdir($filePath, 0777, true);
        file_put_contents(
            $filePath.DIRECTORY_SEPARATOR.'PaymentService.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class PaymentService
{
    public function handle(): void
    {
    }
}
PHP
        );

        $executor = Mockery::mock(CommandExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([
                    'exit_code' => 1,
                    'output' => json_encode([
                        'files' => [
                            'app/Services/PaymentService.php' => [
                                'messages' => [
                                    [
                                        'message' => 'Expected 1 blank line at end of file; 0 found',
                                        'source' => 'PSR12.Files.FileHeader.SpacingAfterBlock',
                                        'severity' => 5,
                                        'fixable' => true,
                                        'type' => 'ERROR',
                                        'line' => 12,
                                        'column' => 1,
                                    ],
                                ],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'error_output' => '',
                ]);
        });

        $linter = new ConventionLinter($executor);
        $result = $linter->run(['app/Services/PaymentService.php'], $projectRoot, LxConfig::load($projectRoot));

        expect($result['issues'])->toHaveCount(1)
            ->and($result['issues'][0]['file'])->toBe('app/Services/PaymentService.php')
            ->and($result['issues'][0]['severity'])->toBe('error')
            ->and($result['issues'][0]['fixable'])->toBeTrue();
    } finally {
        deleteDirectory($projectRoot);
    }
});

test('it runs phpcbf during auto fix', function (): void {
    $projectRoot = testProjectRoot();

    try {
        $executor = Mockery::mock(CommandExecutor::class, function (MockInterface $mock) use ($projectRoot): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(function (array $command, string $workingDirectory) use ($projectRoot): bool {
                    return $workingDirectory === $projectRoot
                        && in_array(base_path('vendor/bin/phpcbf'), $command, true)
                        && in_array('app/Services/PaymentService.php', $command, true);
                })
                ->andReturn([
                    'exit_code' => 0,
                    'output' => 'fixed',
                    'error_output' => '',
                ]);
        });

        $linter = new ConventionLinter($executor);
        $result = $linter->fix(['app/Services/PaymentService.php'], $projectRoot);

        expect($result['exit_code'])->toBe(0);
    } finally {
        deleteDirectory($projectRoot);
    }
});
