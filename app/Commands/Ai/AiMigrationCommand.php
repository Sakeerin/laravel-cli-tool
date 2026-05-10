<?php

declare(strict_types=1);

namespace App\Commands\Ai;

use App\Prompts\MigrationPrompt;
use App\Services\ClaudeService;
use App\Services\UsageTracker;
use LaravelZero\Framework\Commands\Command;

class AiMigrationCommand extends Command
{
    protected $signature = 'ai:migration
                            {description : Natural language description of the migration}
                            {--table= : Explicitly specify the table name}
                            {--dry-run : Show the generated migration without saving it}';

    protected $description = 'Generate a Laravel migration from a natural language description using AI (Pro)';

    public function handle(ClaudeService $claude, UsageTracker $tracker): int
    {
        $description = (string) $this->argument('description');
        $table = (string) ($this->option('table') ?? '');
        $dryRun = (bool) $this->option('dry-run');
        $projectRoot = getcwd() ?: '.';

        try {
            $tracker->checkRateLimit();
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Generating migration...');

        try {
            $code = $claude->complete(
                MigrationPrompt::system(),
                MigrationPrompt::user($description, $table),
            );
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $code = $this->extractPhpCode($code);

        if (! $this->validatePhpSyntax($code)) {
            $this->components->error('Generated code has invalid PHP syntax. Please try again with a clearer description.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->line('');
            $this->line($code);
            $this->line('');
            $this->components->info('(dry-run: file not saved)');

            return self::SUCCESS;
        }

        $tableName = $table !== '' ? $table : $this->extractTableName($code, $description);
        $filename = date('Y_m_d_His').'_create_'.$tableName.'_table.php';
        $migrationDir = $projectRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        if (! is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }

        file_put_contents($migrationDir.DIRECTORY_SEPARATOR.$filename, $code);
        $this->components->info("Created database/migrations/{$filename}");

        $tracker->recordUsage($claude->getLastInputTokens(), $claude->getLastOutputTokens());
        $total = $claude->getLastInputTokens() + $claude->getLastOutputTokens();
        $cost = ($claude->getLastInputTokens() * 3 + $claude->getLastOutputTokens() * 15) / 1_000_000;
        $this->line('  <fg=gray>Used '.number_format($total).' tokens (~$'.number_format($cost, 4).')</>');

        return self::SUCCESS;
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

    private function extractTableName(string $code, string $description): string
    {
        if (preg_match("/Schema::create\('([^']+)'/", $code, $matches)) {
            return $matches[1];
        }

        $words = preg_split('/\s+/', strtolower($description)) ?: [];

        return preg_replace('/[^a-z0-9_]/', '_', implode('_', array_slice($words, 0, 3))) ?? 'new';
    }
}
