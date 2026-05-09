<?php

declare(strict_types=1);

namespace App\Commands\Config;

use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigPullCommand extends Command
{
    protected $signature = 'config:pull
                            {url : URL to fetch the .lxconfig.yml from}
                            {--force : Overwrite existing .lxconfig.yml without asking}';

    protected $description = 'Pull a .lxconfig.yml from a remote URL';

    public function handle(): int
    {
        $projectRoot = getcwd() ?: '.';
        $configPath = $projectRoot.DIRECTORY_SEPARATOR.'.lxconfig.yml';
        $url = (string) $this->argument('url');

        if (is_file($configPath) && ! (bool) $this->option('force')) {
            if (! $this->confirm('.lxconfig.yml already exists. Overwrite?', false)) {
                $this->components->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->line("  Fetching config from {$url}...");

        try {
            $content = $this->fetchUrl($url);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (ParseException $e) {
            $this->components->error("Invalid YAML: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! is_array($parsed)) {
            $this->components->error('Config must be a YAML mapping.');

            return self::FAILURE;
        }

        file_put_contents($configPath, $content);

        $this->components->info('Updated .lxconfig.yml');

        return self::SUCCESS;
    }

    private function fetchUrl(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'user_agent' => 'lx-cli/1.0',
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new RuntimeException("Failed to fetch config from [{$url}].");
        }

        return $content;
    }
}
