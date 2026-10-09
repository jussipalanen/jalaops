<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini through the generateContent endpoint, using only its
 * long-standing request fields (contents and systemInstruction).
 */
class GeminiClient extends AiClient
{
    public static function fromConfig(): self
    {
        return new self(
            config('services.gemini.api_key'),
            config('services.gemini.model'),
            rtrim(config('services.gemini.base_url'), '/'),
            config('services.gemini.timeout'),
        );
    }

    public function provider(): string
    {
        return 'Gemini';
    }

    protected function complete(string $systemInstruction, string $prompt): string
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ]);
        } catch (ConnectionException $exception) {
            throw new AiException('Gemini API could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new AiException("Gemini API returned status {$response->status()}.");
        }

        // Thinking models can return their reasoning as separate "thought" parts; skip those.
        return collect($response->json('candidates.0.content.parts', []))
            ->reject(fn ($part) => ($part['thought'] ?? false) === true)
            ->pluck('text')
            ->implode('');
    }
}
