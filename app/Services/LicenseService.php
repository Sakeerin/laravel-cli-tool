<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LicenseService
{
    private const LICENSE_DIR = '.lx';
    private const LICENSE_FILE = 'license.json';
    private const CACHE_TTL = 86400; // 24 hours
    private const GRACE_PERIOD = 604800; // 7 days

    public function __construct(
        private readonly string $apiUrl = 'https://api.lx.dev',
        private readonly ?string $homeDir = null,
    ) {}

    public function requirePro(): void
    {
        if (!$this->isProActive()) {
            throw new \RuntimeException(
                "This command requires lx Pro.\n" .
                "Activate with: lx license:activate <KEY>\n" .
                "Purchase at: https://lx.dev/pro"
            );
        }
    }

    public function isProActive(): bool
    {
        $license = $this->readLicense();
        if (!$license) {
            return false;
        }

        // Use cache if it's still fresh
        if ($this->isCacheValid($license)) {
            return true;
        }

        // Online validation
        try {
            $valid = $this->validateOnline($license['key']);
            $this->updateCache($license, $valid);
            return $valid;
        } catch (\Exception $e) {
            // Offline grace period
            $lastValidated = $license['last_validated_at'] ?? 0;
            return (time() - $lastValidated) < self::GRACE_PERIOD;
        }
    }

    public function activate(string $key): bool
    {
        if ($this->validateOnline($key)) {
            $this->saveLicense([
                'key' => $key,
                'last_validated_at' => time(),
                'cached_at' => time(),
                'cache_valid' => true,
            ]);
            return true;
        }

        return false;
    }

    public function getLicenseInfo(): ?array
    {
        return $this->readLicense();
    }

    private function validateOnline(string $key): bool
    {
        $response = Http::timeout(5)->post("{$this->apiUrl}/license/validate", [
            'key' => $key,
            'hostname' => gethostname(),
            'version' => '1.0.0', // TODO: Get from config/app.php
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('License server unreachable');
        }

        return $response->json('valid') === true;
    }

    private function readLicense(): ?array
    {
        $path = $this->getLicenseFilePath();
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content ? json_decode($content, true) : null;
    }

    private function saveLicense(array $data): void
    {
        $dir = $this->getLicenseDirPath();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->getLicenseFilePath(), json_encode($data, JSON_PRETTY_PRINT));
    }

    private function isCacheValid(array $license): bool
    {
        return isset($license['cached_at'])
            && (time() - $license['cached_at']) < self::CACHE_TTL
            && ($license['cache_valid'] ?? false) === true;
    }

    private function updateCache(array $license, bool $valid): void
    {
        $license['cached_at'] = time();
        $license['cache_valid'] = $valid;
        if ($valid) {
            $license['last_validated_at'] = time();
        }
        $this->saveLicense($license);
    }

    private function getLicenseDirPath(): string
    {
        $home = $this->homeDir ?? $this->getHomeDir();
        return "{$home}/" . self::LICENSE_DIR;
    }

    private function getLicenseFilePath(): string
    {
        return $this->getLicenseDirPath() . '/' . self::LICENSE_FILE;
    }

    private function getHomeDir(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return getenv('USERPROFILE') ?: (getenv('HOMEDRIVE') . getenv('HOMEPATH'));
        }

        return getenv('HOME') ?: '/tmp';
    }
}
