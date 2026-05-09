<?php

declare(strict_types=1);

namespace App\Commands\Config;

use App\Config\LxConfig;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Yaml;

class ConfigInitCommand extends Command
{
    protected $signature = 'config:init';

    protected $description = 'Create a .lxconfig.yml file with interactive defaults';

    public function handle(): int
    {
        $projectRoot = getcwd() ?: '.';
        $configPath = $projectRoot.DIRECTORY_SEPARATOR.'.lxconfig.yml';

        if (is_file($configPath)) {
            if (! $this->confirm('.lxconfig.yml already exists. Overwrite?', false)) {
                $this->components->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        $defaults = LxConfig::defaults();

        $this->line('');
        $this->line('<options=bold>lx config:init</> — Press <Enter> to accept defaults.');
        $this->line('');

        $servicePath = $this->ask('Service path', $defaults['scaffold']['service_path']);
        $repositoryPath = $this->ask('Repository path', $defaults['scaffold']['repository_path']);
        $dtoPath = $this->ask('DTO path', $defaults['scaffold']['dto_path']);
        $actionPath = $this->ask('Action path', $defaults['scaffold']['action_path']);
        $testPath = $this->ask('Test path', $defaults['scaffold']['test_path']);
        $useReadonlyDto = $this->confirm('Use readonly DTOs by default?', (bool) $defaults['scaffold']['use_readonly_dto']);

        $defaultRequired = implode(', ', $defaults['check']['required_env']);
        $requiredEnvStr = $this->ask('Required .env variables (comma-separated)', $defaultRequired);
        $requiredEnv = array_values(array_filter(array_map('trim', explode(',', $requiredEnvStr))));

        $config = [
            'version' => 1,
            'scaffold' => [
                'service_path' => $servicePath,
                'repository_path' => $repositoryPath,
                'contract_path' => $defaults['scaffold']['contract_path'],
                'dto_path' => $dtoPath,
                'action_path' => $actionPath,
                'test_path' => $testPath,
                'use_readonly_dto' => $useReadonlyDto,
                'use_strict_types' => $defaults['scaffold']['use_strict_types'],
            ],
            'lint' => $defaults['lint'],
            'check' => [
                'required_env' => $requiredEnv,
                'warn_if' => $defaults['check']['warn_if'],
            ],
            'ai' => $defaults['ai'],
        ];

        file_put_contents($configPath, Yaml::dump($config, 5, 2));

        $this->line('');
        $this->components->info('Created .lxconfig.yml');

        return self::SUCCESS;
    }
}
