<?php

declare(strict_types=1);

namespace App\Commands\Lint;

use App\Config\LxConfig;
use App\Services\ConventionLinter;
use LaravelZero\Framework\Commands\Command;

class LintCommand extends Command
{
    protected $signature = 'lint
                            {paths?* : Files or directories to lint}
                            {--fix : Auto-fix issues that PHP_CodeSniffer can repair}
                            {--strict : Treat custom lx rules as errors}
                            {--format=text : Output format: text, json, or github}';

    protected $description = 'Lint PHP files using project conventions and PHP_CodeSniffer';

    public function __construct(
        private readonly ConventionLinter $conventionLinter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectRoot = getcwd() ?: '.';
        $config = LxConfig::load($projectRoot);
        $format = strtolower((string) $this->option('format'));
        $targets = $this->resolveTargets((array) $this->argument('paths'));
        $ignorePatterns = (array) $config->get('lint.ignore', []);

        $files = $this->conventionLinter->discoverPhpFiles($targets, $projectRoot, $ignorePatterns);

        if ($files === []) {
            $emptyResult = [
                'files' => [],
                'issues' => [],
                'summary' => [
                    'errors' => 0,
                    'warnings' => 0,
                    'fixable' => 0,
                ],
            ];

            if ($format === 'json') {
                $this->line((string) json_encode($emptyResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } elseif ($format === 'github') {
                $this->line($this->summaryLine($emptyResult));
            } else {
                $this->components->warn('No PHP files matched the requested lint targets.');
            }

            return self::SUCCESS;
        }

        if ((bool) $this->option('fix')) {
            $this->components->info('Running PHP_CodeSniffer auto-fix...');
            $this->conventionLinter->fix($files, $projectRoot);
        }

        $progressBar = $format === 'text' ? $this->output->createProgressBar(count($files)) : null;

        if ($progressBar !== null) {
            $progressBar->setFormat('debug');
            $progressBar->start();
        }

        $result = $this->conventionLinter->run(
            $files,
            $projectRoot,
            $config,
            (bool) $this->option('strict'),
            $progressBar !== null
                ? function (string $_file) use ($progressBar): void {
                    $progressBar->advance();
                }
            : null,
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $this->renderResult($result, $format);

        return count($result['issues']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<string>  $targets
     * @return list<string>
     */
    private function resolveTargets(array $targets): array
    {
        $targets = array_values(array_filter(array_map('trim', $targets)));

        if ($targets !== []) {
            return $targets;
        }

        return array_values(array_filter([
            is_dir('app') ? 'app' : null,
            is_dir('tests') ? 'tests' : null,
        ]));
    }

    /**
     * @param  array{
     *     files:list<string>,
     *     issues:list<array{file:string,line:int,column:int,message:string,source:string,severity:string,fixable:bool,type:string}>,
     *     summary:array{errors:int,warnings:int,fixable:int}
     * }  $result
     */
    private function renderResult(array $result, string $format): void
    {
        if ($format === 'json') {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        if ($format === 'github') {
            foreach ($result['issues'] as $issue) {
                $level = $issue['severity'] === 'error' ? 'error' : 'warning';
                $message = str_replace(["\r", "\n"], ' ', $issue['message']);

                $this->line("::{$level} file={$issue['file']},line={$issue['line']},col={$issue['column']}::{$message}");
            }

            $this->line($this->summaryLine($result));

            return;
        }

        if ($result['issues'] === []) {
            $this->components->info('No lint issues found.');
            $this->line($this->summaryLine($result));

            return;
        }

        foreach ($result['issues'] as $issue) {
            $level = strtoupper($issue['severity']);
            $this->line("[{$level}] {$issue['file']}:{$issue['line']}:{$issue['column']} {$issue['message']} ({$issue['source']})");
        }

        $this->newLine();
        $this->line($this->summaryLine($result));
    }

    /**
     * @param  array{
     *     summary:array{errors:int,warnings:int,fixable:int}
     * }  $result
     */
    private function summaryLine(array $result): string
    {
        return sprintf(
            'Summary: %d error(s), %d warning(s), %d fixable issue(s)',
            $result['summary']['errors'],
            $result['summary']['warnings'],
            $result['summary']['fixable'],
        );
    }
}
