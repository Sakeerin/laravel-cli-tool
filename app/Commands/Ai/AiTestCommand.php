<?php

declare(strict_types=1);

namespace App\Commands\Ai;

use App\Prompts\TestGenerationPrompt;
use App\Services\ClaudeService;
use App\Services\UsageTracker;
use LaravelZero\Framework\Commands\Command;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test
                            {file : Path to PHP file, optionally with ::MethodName (e.g. app/Services/Foo.php::calculate)}
                            {--pest : Generate Pest syntax (default)}
                            {--phpunit : Generate PHPUnit syntax}
                            {--dry-run : Show generated tests without saving}';

    protected $description = 'Generate tests for a PHP class or method using AI (Pro)';

    public function handle(ClaudeService $claude, UsageTracker $tracker): int
    {
        $fileArg = (string) $this->argument('file');
        $projectRoot = getcwd() ?: '.';
        $framework = (bool) $this->option('phpunit') ? 'phpunit' : 'pest';

        $method = null;

        if (str_contains($fileArg, '::')) {
            [$fileArg, $method] = explode('::', $fileArg, 2);
        }

        $filePath = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $fileArg);

        if (! is_file($filePath)) {
            $this->components->error("File not found: {$fileArg}");

            return self::FAILURE;
        }

        $source = (string) file_get_contents($filePath);

        if ($method !== null) {
            $extracted = $this->extractMethod($source, $method);

            if ($extracted === '') {
                $this->components->error("Method '{$method}' not found in {$fileArg}");

                return self::FAILURE;
            }

            $source = $extracted;
        }

        try {
            $tracker->checkRateLimit();
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Reading {$fileArg}...");
        $this->line('Generating tests...');

        try {
            $testCode = $claude->complete(
                TestGenerationPrompt::system($framework),
                TestGenerationPrompt::user($source, $method),
            );
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $testCode = $this->extractPhpCode($testCode);

        if (! $this->validatePhpSyntax($testCode)) {
            $this->components->error('Generated test has invalid PHP syntax. Please try again.');

            return self::FAILURE;
        }

        if ((bool) $this->option('dry-run')) {
            $this->line('');
            $this->line($testCode);

            return self::SUCCESS;
        }

        $testPath = $this->resolveTestPath($fileArg, $projectRoot);
        $testDir = dirname($testPath);

        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        file_put_contents($testPath, $testCode);
        $relativePath = str_replace('\\', '/', ltrim(str_replace($projectRoot, '', $testPath), DIRECTORY_SEPARATOR.'/'));
        $this->components->info("Created {$relativePath}");

        $tracker->recordUsage($claude->getLastInputTokens(), $claude->getLastOutputTokens());
        $total = $claude->getLastInputTokens() + $claude->getLastOutputTokens();
        $this->line('  <fg=gray>Used '.number_format($total).' tokens</>');

        return self::SUCCESS;
    }

    private function extractMethod(string $source, string $method): string
    {
        $pattern = '/(?:public|protected|private)\s+(?:static\s+)?function\s+'.preg_quote($method, '/').'\s*\([^{]*(?:\{(?:[^{}]|\{[^{}]*\})*\})/s';

        if (preg_match($pattern, $source, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function resolveTestPath(string $fileArg, string $projectRoot): string
    {
        $parts = explode('/', str_replace(['\\', DIRECTORY_SEPARATOR], '/', $fileArg));
        $filename = array_pop($parts);
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        if (($parts[0] ?? '') === 'app') {
            array_shift($parts);
        }

        $testFile = $baseName.'Test.php';
        $testDir = $projectRoot.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit';

        if ($parts !== []) {
            $testDir .= DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);
        }

        return $testDir.DIRECTORY_SEPARATOR.$testFile;
    }

    private function extractPhpCode(string $response): string
    {
        $response = preg_replace('/^```php\s*/m', '', $response) ?? $response;
        $response = preg_replace('/^```\s*$/m', '', $response) ?? $response;

        return trim($response);
    }

    private function validatePhpSyntax(string $code): bool
    {
        if (! function_exists('exec')) {
            return true;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'lx_').'.php';
        file_put_contents($tmpFile, $code);
        exec(PHP_BINARY.' -l '.escapeshellarg($tmpFile).' 2>&1', $output, $exitCode);
        @unlink($tmpFile);

        return $exitCode === 0;
    }
}
