<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * A text-generation provider that answers a prompt with JSON.
 *
 * The prompt describes the JSON it wants; the reply is parsed here and
 * checked by the caller. This keeps the clients independent of each
 * provider's structured-output options, which change between API versions.
 */
abstract class AiClient
{
    public function __construct(
        protected readonly ?string $apiKey,
        protected readonly string $model,
        protected readonly string $baseUrl,
        protected readonly int $timeout,
    ) {}

    /**
     * The model that wrote the last reply, when it wasn't the configured one.
     */
    protected ?string $repliedModel = null;

    /**
     * Display name of the provider, e.g. "Gemini".
     */
    abstract public function provider(): string;

    /**
     * Sends the prompt and returns the reply text.
     *
     * @throws AiException
     */
    abstract protected function complete(string $systemInstruction, string $prompt): string;

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * The model that wrote the last reply: the configured one, or a fallback.
     */
    public function repliedModel(): string
    {
        return $this->repliedModel ?? $this->model;
    }

    /**
     * Sends the prompt and returns the reply decoded from JSON.
     *
     * @return array<mixed>
     *
     * @throws AiException when the request fails or the reply isn't JSON
     */
    public function generateJson(string $systemInstruction, string $prompt): array
    {
        if (! $this->isConfigured()) {
            throw new AiException("{$this->provider()} API key is not set.");
        }

        $decoded = json_decode($this->stripCodeFence($this->complete($systemInstruction, $prompt)), true);

        if (! is_array($decoded)) {
            throw new AiException("{$this->provider()} reply was not valid JSON.");
        }

        return $decoded;
    }

    /**
     * Logs how long the provider took, split into DNS lookup, connecting and
     * waiting for the reply, to tell network delays from slow generation.
     */
    protected function logTiming(Response $response, ?string $model = null): void
    {
        $stats = $response->handlerStats();

        Log::info(sprintf(
            '%s (%s) replied with status %d in %.2f s (DNS %.2f s, connect %.2f s, TLS %.2f s, first byte %.2f s).',
            $this->provider(),
            $model ?? $this->model,
            $response->status(),
            $stats['total_time'] ?? 0,
            $stats['namelookup_time'] ?? 0,
            $stats['connect_time'] ?? 0,
            $stats['appconnect_time'] ?? 0,
            $stats['starttransfer_time'] ?? 0,
        ));
    }

    /**
     * Models sometimes wrap JSON in a ```json … ``` block despite the instructions.
     */
    private function stripCodeFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
            return $matches[1];
        }

        return $text;
    }
}
