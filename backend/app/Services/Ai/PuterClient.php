<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Puter AI through its OpenAI-compatible chat completions endpoint. The auth
 * token comes from the Puter dashboard (puter.com/dashboard → Create token),
 * and the model can be any model Puter offers (developer.puter.com/ai/models).
 * The default, google/gemma-4-31b-it, is free on Puter.
 */
class PuterClient extends AiClient
{
    public static function fromConfig(): self
    {
        return new self(
            config('services.puter.auth_token'),
            config('services.puter.model'),
            rtrim(config('services.puter.base_url'), '/'),
            config('services.puter.timeout'),
        );
    }

    public function provider(): string
    {
        return 'Puter';
    }

    protected function complete(string $systemInstruction, string $prompt): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    // One user message instead of a separate system message: some
                    // models on Puter (e.g. Gemma) don't accept the system role.
                    'messages' => [
                        ['role' => 'user', 'content' => "{$systemInstruction}\n\n{$prompt}"],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new AiException('Puter API could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new AiException("Puter API returned status {$response->status()}.");
        }

        return (string) $response->json('choices.0.message.content', '');
    }
}
