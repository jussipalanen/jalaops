<?php

namespace App\Services\Ai;

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
