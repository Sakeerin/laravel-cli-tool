<?php

declare(strict_types=1);

namespace App\Commands\Ai;

use App\Prompts\ReviewPrompt;
use App\Services\ClaudeService;
use App\Services\CommandExecutor;
use App\Services\UsageTracker;
use LaravelZero\Framework\Commands\Command;

class AiReviewCommand extends Command
{
    protected $signature = 'ai:review
                            {--staged : Review staged changes (git diff --cached)}
                            {--diff : Review uncommitted changes (git diff)}
                            {--file= : Review a specific file}';

    protected $description = 'Review code or a diff using AI (Pro)';

    public function handle(ClaudeService $claude, UsageTracker $tracker, CommandExecutor $executor): int
    {
        $projectRoot = getcwd() ?: '.';
        $diffContent = '';

        if ((bool) $this->option('staged')) {
            $result = $executor->execute(['git', 'diff', '--cached'], $projectRoot);
            $diffContent = $result['output'];
        } elseif ((bool) $this->option('diff')) {
            $result = $executor->execute(['git', 'diff'], $projectRoot);
            $diffContent = $result['output'];
        } elseif ($this->option('file') !== null) {
            $filePath = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $this->option('file'));

            if (! is_file($filePath)) {
                $this->components->error('File not found: '.(string) $this->option('file'));

                return self::FAILURE;
            }

            $diffContent = (string) file_get_contents($filePath);
        } else {
            $this->components->error('Please specify --staged, --diff, or --file=<path>');

            return self::FAILURE;
        }

        if (trim($diffContent) === '') {
            $this->components->info('No changes to review.');

            return self::SUCCESS;
        }

        try {
            $tracker->checkRateLimit();
        } catch (\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Reviewing changes...');
        $this->line('');

        try {
            $claude->streamText(
                ReviewPrompt::system(),
                ReviewPrompt::user($diffContent),
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
}
