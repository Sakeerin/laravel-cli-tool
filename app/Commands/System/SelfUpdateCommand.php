<?php

declare(strict_types=1);

namespace App\Commands\System;

use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class SelfUpdateCommand extends Command
{
    protected $signature = 'self-update';

    protected $description = 'Update the lx CLI to the latest version';

    private const REPO = 'Sakeerin/laravel-cli-tool';

    public function handle(): int
    {
        $currentVersion = config('app.version', '1.0.0');
        $this->info("Current version: {$currentVersion}");

        $latestRelease = $this->getLatestRelease();

        if (!$latestRelease) {
            $this->error('Could not fetch latest release from GitHub.');
            return 1;
        }

        $latestVersion = ltrim($latestRelease['tag_name'], 'v');

        if (version_compare($currentVersion, $latestVersion, '>=')) {
            $this->info('You are already using the latest version.');
            return 0;
        }

        $this->info("New version available: {$latestVersion}");

        if (!$this->confirm('Do you want to update?', true)) {
            return 0;
        }

        $asset = $this->findPharAsset($latestRelease['assets']);

        if (!$asset) {
            $this->error('No .phar asset found in the latest release.');
            return 1;
        }

        $this->info("Downloading update from: {$asset['browser_download_url']}");

        try {
            $this->updateBinary($asset['browser_download_url']);
            $this->info('✓ Updated successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error("Update failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function getLatestRelease(): ?array
    {
        $response = Http::get("https://api.github.com/repos/" . self::REPO . "/releases/latest");

        return $response->successful() ? $response->json() : null;
    }

    private function findPharAsset(array $assets): ?array
    {
        foreach ($assets as $asset) {
            if (str_ends_with($asset['name'], '.phar')) {
                return $asset;
            }
        }

        return null;
    }

    private function updateBinary(string $url): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lx_update');
        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download the new version.');
        }

        file_put_contents($tempFile, $response->body());

        $currentBinary = $_SERVER['argv'][0];
        $realPath = realpath($currentBinary);

        if (!$realPath || !is_writable($realPath)) {
            // If we can't write to the current binary (e.g. installed via composer),
            // we might not be able to self-update this way.
            throw new \RuntimeException("Current binary is not writable: {$currentBinary}");
        }

        if (!rename($tempFile, $realPath)) {
            throw new \RuntimeException('Failed to replace the current binary.');
        }

        chmod($realPath, 0755);
    }
}
