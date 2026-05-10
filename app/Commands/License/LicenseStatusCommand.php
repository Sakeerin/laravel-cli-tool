<?php

declare(strict_types=1);

namespace App\Commands\License;

use App\Services\LicenseService;
use LaravelZero\Framework\Commands\Command;

class LicenseStatusCommand extends Command
{
    protected $signature = 'license:status';

    protected $description = 'Display your lx Pro license status';

    public function handle(LicenseService $license): int
    {
        $info = $license->getLicenseInfo();

        if (!$info) {
            $this->warn('No license active. Purchase Pro at https://lx.dev/pro');
            return 0;
        }

        $status = $license->isProActive() ? '<info>Active</info>' : '<error>Inactive/Expired</error>';
        $maskedKey = substr($info['key'], 0, 7) . str_repeat('*', strlen($info['key']) - 7);

        $this->table(
            ['Field', 'Value'],
            [
                ['License Key', $maskedKey],
                ['Status', $status],
                ['Last Validated', date('Y-m-d H:i:s', $info['last_validated_at'] ?? 0)],
                ['Cached Until', date('Y-m-d H:i:s', ($info['cached_at'] ?? 0) + 86400)],
            ]
        );

        return 0;
    }
}
