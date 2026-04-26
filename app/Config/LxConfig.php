<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class LxConfig
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public static function load(?string $projectRoot = null): self
    {
        $projectRoot ??= getcwd() ?: '.';
        $configPath = rtrim($projectRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.lxconfig.yml';
        $defaults = self::defaults();

        if (! is_file($configPath)) {
            return new self($defaults);
        }

        try {
            $parsed = Yaml::parseFile($configPath);
        } catch (ParseException $exception) {
            throw new RuntimeException("Unable to parse .lxconfig.yml at [{$configPath}].", 0, $exception);
        }

        if (! is_array($parsed)) {
            throw new RuntimeException(".lxconfig.yml at [{$configPath}] must contain a YAML mapping.");
        }

        return new self(self::merge($defaults, $parsed));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'version' => 1,
            'scaffold' => [
                'service_path' => 'app/Services',
                'repository_path' => 'app/Repositories',
                'contract_path' => 'app/Contracts',
                'dto_path' => 'app/Data',
                'action_path' => 'app/Actions',
                'test_path' => 'tests/Unit',
                'use_readonly_dto' => true,
                'use_strict_types' => true,
            ],
            'lint' => [
                'rules' => [
                    'require_return_types' => true,
                    'require_strict_types' => true,
                    'require_docblock_for' => [],
                    'max_method_length' => 30,
                    'naming' => [
                        'service_suffix' => 'Service',
                        'repository_suffix' => 'Repository',
                        'dto_suffix' => 'Data',
                    ],
                ],
                'ignore' => [],
            ],
            'check' => [
                'required_env' => [
                    'APP_KEY',
                    'DB_CONNECTION',
                    'QUEUE_CONNECTION',
                    'CACHE_DRIVER',
                ],
                'warn_if' => [
                    [
                        'key' => 'APP_DEBUG',
                        'value' => 'true',
                        'message' => 'APP_DEBUG should be false in production',
                    ],
                    [
                        'key' => 'QUEUE_CONNECTION',
                        'value' => 'sync',
                        'message' => 'Use redis or database queue in production',
                    ],
                ],
            ],
            'ai' => [
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 4096,
                'migration_style' => 'fluent',
                'test_framework' => 'pest',
                'review_focus' => [
                    'n_plus_one',
                    'missing_indexes',
                    'security',
                    'naming_convention',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    public function servicePath(): string
    {
        return $this->scaffoldPath('service_path');
    }

    public function repositoryPath(): string
    {
        return $this->scaffoldPath('repository_path');
    }

    public function contractPath(): string
    {
        return $this->scaffoldPath('contract_path');
    }

    public function dtoPath(): string
    {
        return $this->scaffoldPath('dto_path');
    }

    public function actionPath(): string
    {
        return $this->scaffoldPath('action_path');
    }

    public function testPath(): string
    {
        return $this->scaffoldPath('test_path');
    }

    public function scaffoldPath(string $key): string
    {
        $path = $this->get("scaffold.{$key}");

        return is_string($path) ? trim($path, '/\\') : '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function merge(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = self::merge($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
