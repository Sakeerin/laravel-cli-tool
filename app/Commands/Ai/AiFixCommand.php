<?php

declare(strict_types=1);

namespace App\Commands\Ai;

use App\Prompts\FixPrompt;
use App\Services\ClaudeService;
use App\Services\UsageTracker;
use LaravelZero\Framework\Commands\Command;

class AiFixCommand extends Command
{
    protected $signature = 'ai:fix
                            {error_message? : The error message to analyze}
                            {--last : Read the most recent error from storage/logs/laravel.log}';

    protected $description = 'Analyze a Laravel error and suggest a fix using AI (Pro)';

    public function handle(ClaudeService $claude, UsageTracker $tracker): int
    {
        $projectRoot = getcwd() ?: '.';
        $errorMessage = (string) ($this->argument('error_message') ?? '');

        if ((bool) $this->option('last')) {
            $logPath = $projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'laravel.log';

            if (! is_file($logPath)) {
                $this->components->error('No laravel.log found at storage/logs/laravel.log');

                return self::FAILURE;
            }

            $errorMessage = $this->extractLastError($logPath);
        }

        if (trim($errorMessage) === '') {
            $this->components->error('No error message provided. Use: lx ai:fix "error message" or lx ai:fix --last');

            return self::FAILURE;
        }

        try {
            $tracker->checkRateLimit();
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Analyzing error...');
        $this->line('');

        try {
            $claude->streamText(
                FixPrompt::system(),
                FixPrompt::user($errorMessage),
                fn (string $chunk) => $this->output->write($chunk),
            );
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->line('');

        $tracker->recordUsage($claude->getLastInputTokens(), $claude->getLastOutputTokens());
        $total = $claude->getLastInputTokens() + $claude->getLastOutputTokens();
        $this->line('<fg=gray>Used '.number_format($total).' tokens</>');

        return self::SUCCESS;
    }

    private function extractLastError(string $logPath): string
    {
        $lines = file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
        $recent = array_slice($lines, -50);

        $lastErrorIdx = -1;

        foreach ($recent as $i => $line) {
            if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line)) {
                $lastErrorIdx = $i;
            }
        }

        if ($lastErrorIdx === -1) {
            return implode("\n", array_slice($recent, -10));
        }

        return implode("\n", array_slice($recent, $lastErrorIdx, min(15, count($recent) - $lastErrorIdx)));
    }
}
