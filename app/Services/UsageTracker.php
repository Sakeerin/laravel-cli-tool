<?php

declare(strict_types=1);

namespace App\Services;

class UsageTracker
{
    private const MAX_CALLS_PER_MINUTE = 10;

    private function getLxDir(): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? sys_get_temp_dir();

        return $home.DIRECTORY_SEPARATOR.'.lx';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $filename): array
    {
        $path = $this->getLxDir().DIRECTORY_SEPARATOR.$filename;

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $filename, array $data): void
    {
        $dir = $this->getLxDir();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.$filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function checkRateLimit(): void
    {
        $data = $this->readJson('rate.json');
        $calls = is_array($data['calls'] ?? null) ? $data['calls'] : [];

        $now = time();
        $calls = array_values(array_filter($calls, fn ($t): bool => $now - (int) $t < 60));

        if (count($calls) >= self::MAX_CALLS_PER_MINUTE) {
            throw new \RuntimeException(
                'Rate limit exceeded: max '.self::MAX_CALLS_PER_MINUTE.' AI calls per minute. Please wait before trying again.'
            );
        }

        $calls[] = $now;
        $this->writeJson('rate.json', ['calls' => $calls]);
    }

    public function recordUsage(int $inputTokens, int $outputTokens): void
    {
        $data = $this->readJson('usage.json');
        $month = date('Y-m');

        if (! isset($data[$month]) || ! is_array($data[$month])) {
            $data[$month] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
        }

        $data[$month]['input_tokens'] = (int) ($data[$month]['input_tokens'] ?? 0) + $inputTokens;
        $data[$month]['output_tokens'] = (int) ($data[$month]['output_tokens'] ?? 0) + $outputTokens;
        $data[$month]['calls'] = (int) ($data[$month]['calls'] ?? 0) + 1;

        $this->writeJson('usage.json', $data);
    }

    /**
     * @return array{input_tokens: int, output_tokens: int, calls: int}
     */
    public function getMonthlyStats(?string $month = null): array
    {
        $month = $month ?? date('Y-m');
        $data = $this->readJson('usage.json');

        if (! isset($data[$month]) || ! is_array($data[$month])) {
            return ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
        }

        return [
            'input_tokens' => (int) ($data[$month]['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data[$month]['output_tokens'] ?? 0),
            'calls' => (int) ($data[$month]['calls'] ?? 0),
        ];
    }
}
