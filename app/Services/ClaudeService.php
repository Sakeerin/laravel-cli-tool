<?php

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;

class ClaudeService
{
    private const MODEL = 'claude-sonnet-4-6';

    private const MAX_TOKENS = 4096;

    private int $lastInputTokens = 0;

    private int $lastOutputTokens = 0;

    public function __construct(
        private readonly LicenseService $license,
        private readonly ?string $apiKey = null,
    ) {
        $this->license->requirePro();
    }

    private function getClient(): Client
    {
        $key = $this->apiKey ?? (string) (getenv('ANTHROPIC_API_KEY') ?: '');

        if ($key === '') {
            throw new \RuntimeException(
                "ANTHROPIC_API_KEY is not set.\n".
                "Export it: export ANTHROPIC_API_KEY=sk-ant-...\n".
                "Or add it to your shell profile."
            );
        }

        return new Client(apiKey: $key);
    }

    /**
     * Stream a response from Claude, calling $onChunk for each text chunk.
     *
     * @param callable(string): void $onChunk
     */
    public function streamText(string $system, string $prompt, callable $onChunk): string
    {
        $stream = $this->getClient()->messages->createStream(
            maxTokens: self::MAX_TOKENS,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: self::MODEL,
            system: $system,
        );

        $full = '';
        $this->lastInputTokens = 0;
        $this->lastOutputTokens = 0;

        foreach ($stream as $event) {
            if ($event instanceof RawMessageStartEvent) {
                $this->lastInputTokens = $event->message->usage->inputTokens;
            } elseif ($event instanceof RawMessageDeltaEvent) {
                $this->lastOutputTokens = $event->usage->outputTokens;
            } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                $chunk = $event->delta->text;
                $full .= $chunk;
                $onChunk($chunk);
            }
        }

        return $full;
    }

    /**
     * Get a non-streaming response from Claude.
     */
    public function complete(string $system, string $prompt): string
    {
        $message = $this->getClient()->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: self::MODEL,
            system: $system,
        );

        $this->lastInputTokens = $message->usage->inputTokens;
        $this->lastOutputTokens = $message->usage->outputTokens;

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                return $block->text;
            }
        }

        return '';
    }

    public function getLastInputTokens(): int
    {
        return $this->lastInputTokens;
    }

    public function getLastOutputTokens(): int
    {
        return $this->lastOutputTokens;
    }
}
