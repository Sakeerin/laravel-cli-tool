<?php

declare(strict_types=1);

namespace App\Commands\Check;

use App\Config\LxConfig;
use App\Services\HealthChecker;
use LaravelZero\Framework\Commands\Command;

class CheckCommand extends Command
{
    protected $signature = 'check
                            {--fix : Show suggested fix commands for failed checks}';

    protected $description = 'Check project health (PHP version, .env completeness, security advisories, configuration)';

    public function __construct(
        private readonly HealthChecker $healthChecker,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectRoot = getcwd() ?: '.';
        $config = LxConfig::load($projectRoot);

        $results = $this->healthChecker->run($projectRoot, $config);

        /** @var array<string, list<array{category: string, status: string, label: string, message: string, fix: string|null}>> $grouped */
        $grouped = [];

        foreach ($results as $result) {
            $grouped[$result['category']][] = $result;
        }

        $hasFails = false;
        $fixes = [];

        foreach ($grouped as $category => $items) {
            $this->line('');
            $this->line("  <fg=yellow;options=bold>{$category}:</>");

            foreach ($items as $item) {
                $icon = match ($item['status']) {
                    'pass' => '<fg=green>✓</>',
                    'fail' => '<fg=red>✗</>',
                    'warn' => '<fg=yellow>⚠</>',
                    default => ' ',
                };

                $this->line("  {$icon} {$item['message']}");

                if ($item['status'] === 'fail') {
                    $hasFails = true;
                }

                if ($item['fix'] !== null) {
                    $fixes[] = ['label' => $item['label'], 'fix' => $item['fix']];
                }
            }
        }

        $this->line('');

        if ((bool) $this->option('fix') && $fixes !== []) {
            $this->line('  <options=bold>Suggested fixes:</>');

            foreach ($fixes as $fix) {
                $this->line("  → [{$fix['label']}] {$fix['fix']}");
            }

            $this->line('');
        }

        return $hasFails ? self::FAILURE : self::SUCCESS;
    }
}
