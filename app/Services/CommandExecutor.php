<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

class CommandExecutor
{
    /**
     * @param  list<string>  $command
     * @return array{exit_code:int, output:string, error_output:string}
     */
    public function execute(array $command, ?string $workingDirectory = null): array
    {
        $process = new Process($command, $workingDirectory);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
        ];
    }
}
