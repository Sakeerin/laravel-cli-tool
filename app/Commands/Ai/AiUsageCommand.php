<?php

declare(strict_types=1);

namespace App\Commands\Ai;

use App\Services\UsageTracker;
use LaravelZero\Framework\Commands\Command;

class AiUsageCommand extends Command
{
    protected $signature = 'ai:usage
                            {--month= : Show usage for a specific month (format: YYYY-MM, default: current month)}';

    protected $description = 'Show AI token usage and estimated cost for the current month';

    public function handle(UsageTracker $tracker): int
    {
        $month = (string) ($this->option('month') ?? date('Y-m'));
        $stats = $tracker->getMonthlyStats($month);

        $inputTokens = $stats['input_tokens'];
        $outputTokens = $stats['output_tokens'];
        $calls = $stats['calls'];
        $totalTokens = $inputTokens + $outputTokens;

        // claude-sonnet-4-6: ~$3/MTok input, ~$15/MTok output
        $cost = ($inputTokens * 3 + $outputTokens * 15) / 1_000_000;

        $this->line('');
        $this->line("  <options=bold>AI Usage — {$month}</>");
        $this->line('');
        $this->line("  AI calls:       {$calls}");
        $this->line('  Input tokens:   '.number_format($inputTokens));
        $this->line('  Output tokens:  '.number_format($outputTokens));
        $this->line('  Total tokens:   '.number_format($totalTokens));
        $this->line('  Estimated cost: $'.number_format($cost, 4));
        $this->line('');

        return self::SUCCESS;
    }
}
