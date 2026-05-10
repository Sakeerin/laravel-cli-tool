<?php

declare(strict_types=1);

namespace App\Commands\License;

use App\Services\LicenseService;
use LaravelZero\Framework\Commands\Command;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {key : The license key (LX-PRO-XXXX-XXXX)}';

    protected $description = 'Activate your lx Pro license';

    public function handle(LicenseService $license): int
    {
        $key = $this->argument('key');

        $this->info("Activating license: {$key}...");

        if ($license->activate($key)) {
            $this->info('✓ License activated successfully!');
            return 0;
        }

        $this->error('✗ Invalid license key or server unreachable.');
        return 1;
    }
}
