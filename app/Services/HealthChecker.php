<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\LxConfig;
use Composer\Semver\Semver;

class HealthChecker
{
    public function __construct(
        private readonly CommandExecutor $executor,
    ) {}

    /**
     * @return list<array{category: string, status: string, label: string, message: string, fix: string|null}>
     */
    public function run(string $projectRoot, LxConfig $config): array
    {
        return array_merge(
            $this->checkPhpVersion($projectRoot),
            $this->checkEnvVariables($projectRoot, $config),
            $this->checkSecurityAdvisories($projectRoot),
            $this->checkConfiguration($projectRoot, $config),
        );
    }

    /**
     * @return list<array{category: string, status: string, label: string, message: string, fix: string|null}>
     */
    private function checkPhpVersion(string $projectRoot): array
    {
        $category = 'Environment';
        $composerPath = $projectRoot.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerPath)) {
            return [['category' => $category, 'status' => 'warn', 'label' => 'PHP version', 'message' => 'composer.json not found', 'fix' => null]];
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        $required = is_array($composer) ? ($composer['require']['php'] ?? null) : null;

        if (! is_string($required)) {
            return [['category' => $category, 'status' => 'warn', 'label' => 'PHP version', 'message' => 'No PHP version constraint in composer.json', 'fix' => null]];
        }

        $current = PHP_VERSION;
        $satisfies = Semver::satisfies($current, $required);

        return [[
            'category' => $category,
            'status' => $satisfies ? 'pass' : 'fail',
            'label' => 'PHP version',
            'message' => $satisfies
                ? "PHP {$current} satisfies {$required}"
                : "PHP {$current} does not satisfy {$required}",
            'fix' => $satisfies ? null : "Upgrade PHP to satisfy {$required}",
        ]];
    }

    /**
     * @return list<array{category: string, status: string, label: string, message: string, fix: string|null}>
     */
    private function checkEnvVariables(string $projectRoot, LxConfig $config): array
    {
        $category = 'Environment';
        $envPath = $projectRoot.DIRECTORY_SEPARATOR.'.env';
        $examplePath = $projectRoot.DIRECTORY_SEPARATOR.'.env.example';

        if (! is_file($envPath)) {
            return [['category' => $category, 'status' => 'fail', 'label' => '.env file', 'message' => '.env file not found', 'fix' => 'cp .env.example .env && php artisan key:generate']];
        }

        $results = [];
        $envValues = $this->parseEnvValues($envPath);

        // Check required env keys from config
        $requiredKeys = $config->get('check.required_env', []);

        if (is_array($requiredKeys) && $requiredKeys !== []) {
            $missing = array_values(array_filter($requiredKeys, static fn (string $key): bool => ! array_key_exists($key, $envValues) || $envValues[$key] === ''));

            $results[] = [
                'category' => $category,
                'status' => $missing === [] ? 'pass' : 'fail',
                'label' => 'Required .env variables',
                'message' => $missing === []
                    ? 'All required variables are set'
                    : 'Missing required variable(s): '.implode(', ', $missing),
                'fix' => $missing === [] ? null : 'Add missing variables to .env',
            ];
        }

        // Check completeness vs .env.example
        if (is_file($examplePath)) {
            $exampleKeys = $this->parseEnvKeys($examplePath);
            $currentKeys = $this->parseEnvKeys($envPath);
            $notPresent = array_values(array_diff($exampleKeys, $currentKeys));

            $results[] = [
                'category' => $category,
                'status' => $notPresent === [] ? 'pass' : 'warn',
                'label' => '.env completeness',
                'message' => $notPresent === []
                    ? 'All .env.example variables are present in .env'
                    : count($notPresent).' variable(s) from .env.example are missing: '.implode(', ', $notPresent),
                'fix' => $notPresent === [] ? null : 'Copy missing variables from .env.example to .env',
            ];
        }

        return $results;
    }

    /**
     * @return list<array{category: string, status: string, label: string, message: string, fix: string|null}>
     */
    private function checkSecurityAdvisories(string $projectRoot): array
    {
        $category = 'Security';
        $result = $this->executor->execute(['composer', 'audit', '--format=json', '--no-interaction'], $projectRoot);

        if ($result['exit_code'] > 1 || $result['output'] === '') {
            return [['category' => $category, 'status' => 'warn', 'label' => 'Security advisories', 'message' => 'composer audit could not be completed', 'fix' => 'Run: composer audit']];
        }

        $data = json_decode($result['output'], true);

        if (! is_array($data)) {
            return [['category' => $category, 'status' => 'warn', 'label' => 'Security advisories', 'message' => 'Unable to parse composer audit output', 'fix' => 'Run: composer audit']];
        }

        $advisories = $data['advisories'] ?? [];
        $count = (int) array_sum(array_map('count', $advisories));

        if ($count === 0) {
            return [['category' => $category, 'status' => 'pass', 'label' => 'Security advisories', 'message' => 'No known vulnerabilities found', 'fix' => null]];
        }

        $packages = implode(', ', array_keys($advisories));

        return [[
            'category' => $category,
            'status' => 'fail',
            'label' => 'Security advisories',
            'message' => "{$count} known vulnerability/ies in: {$packages}",
            'fix' => 'Run: composer update',
        ]];
    }

    /**
     * @return list<array{category: string, status: string, label: string, message: string, fix: string|null}>
     */
    private function checkConfiguration(string $projectRoot, LxConfig $config): array
    {
        $category = 'Configuration';
        $envPath = $projectRoot.DIRECTORY_SEPARATOR.'.env';

        if (! is_file($envPath)) {
            return [];
        }

        $results = [];
        $envValues = $this->parseEnvValues($envPath);

        // APP_KEY check
        $appKey = $envValues['APP_KEY'] ?? '';
        $results[] = [
            'category' => $category,
            'status' => $appKey !== '' ? 'pass' : 'fail',
            'label' => 'APP_KEY',
            'message' => $appKey !== '' ? 'APP_KEY is set' : 'APP_KEY is not set',
            'fix' => $appKey !== '' ? null : 'Run: php artisan key:generate',
        ];

        // warn_if rules from config
        $warnRules = $config->get('check.warn_if', []);

        if (is_array($warnRules)) {
            foreach ($warnRules as $rule) {
                if (! is_array($rule)) {
                    continue;
                }

                $key = (string) ($rule['key'] ?? '');
                $warnValue = (string) ($rule['value'] ?? '');
                $message = (string) ($rule['message'] ?? '');

                if ($key === '' || ! array_key_exists($key, $envValues)) {
                    continue;
                }

                $currentValue = $envValues[$key];
                $isWarning = strtolower($currentValue) === strtolower($warnValue);

                $results[] = [
                    'category' => $category,
                    'status' => $isWarning ? 'warn' : 'pass',
                    'label' => $key,
                    'message' => $isWarning
                        ? "{$key}={$currentValue} — {$message}"
                        : "{$key}={$currentValue}",
                    'fix' => $isWarning ? "Update {$key} in .env" : null,
                ];
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function parseEnvKeys(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $keys = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $keys[] = trim(explode('=', $line, 2)[0]);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvValues(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $values[trim($key)] = trim($value, "\"' ");
        }

        return $values;
    }
}
