<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Google Gemini through the generateContent endpoint, using only its
 * long-standing request fields (contents and systemInstruction).
 *
 * Gemini answers 503 when a model is overloaded, often only for a moment, and
 * 429 when the model's quota is used up. A 503 is tried once more after a
 * pause; if the model is still busy, the fallback model answers instead. It has
 * capacity and a free-tier quota of its own.
 */
class GeminiClient extends AiClient
{
    private const RETRY_AFTER_SECONDS = 2;

    public function __construct(
        ?string $apiKey,
        string $model,
        string $baseUrl,
        int $timeout,
        private readonly ?string $fallbackModel = null,
    ) {
        parent::__construct($apiKey, $model, $baseUrl, $timeout);
    }

    public static function fromConfig(): self
    {
        return new self(
            config('services.gemini.api_key'),
            config('services.gemini.model'),
            rtrim(config('services.gemini.base_url'), '/'),
            config('services.gemini.timeout'),
            config('services.gemini.fallback_model'),
        );
    }

    public function provider(): string
    {
        return 'Gemini';
    }

    protected function complete(string $systemInstruction, string $prompt): string
    {
        $model = $this->model;
        $response = $this->send($model, $systemInstruction, $prompt);

        if ($response->status() === 503) {
            Sleep::for(self::RETRY_AFTER_SECONDS)->seconds();
            $response = $this->send($model, $systemInstruction, $prompt);
        }

        if (in_array($response->status(), [429, 503], true) && filled($this->fallbackModel) && $this->fallbackModel !== $model) {
            $model = $this->fallbackModel;
            $response = $this->send($model, $systemInstruction, $prompt);
        }

        if ($response->failed()) {
            throw new AiException(trim("Gemini API ({$model}) returned status {$response->status()}: ".$response->json('error.message', '')));
        }

        $this->repliedModel = $model;

        // Thinking models can return their reasoning as separate "thought" parts; skip those.
        return collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($part) => ($part['thought'] ?? false) === true)
            ->pluck('text')
            ->implode('');
    }

    private function send(string $model, string $systemInstruction, string $prompt): Response
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ]);
        } catch (ConnectionException $exception) {
            throw new AiException('Gemini API could not be reached.', previous: $exception);
        }

        $this->logTiming($response, $model);

        return $response;
    }
}
