<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    public function __construct()
    {
        $this->apiKey = config('groq.api_key', '');
        $this->model = config('groq.model', 'llama-3.3-70b-versatile');
        $this->timeout = config('groq.timeout', 60);
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Chat with Groq API. Uses OpenAI-compatible format.
     * Returns ['content' => '...'] to match OllamaService/GeminiService response format.
     */
    public function chat(array $messages, array $options = []): ?array
    {
        if (!$this->isAvailable()) return null;

        try {
            $payload = [
                'model' => $options['groq_model'] ?? $this->model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 4096,
            ];

            if (!empty($options['format']) && $options['format'] === 'json') {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $timeout = $options['timeout'] ?? $this->timeout;
            $attempt = 0;
            $maxAttempts = 2;

            do {
                $attempt++;
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($this->baseUrl . '/chat/completions', $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $text = $data['choices'][0]['message']['content'] ?? '';

                    if (empty($text)) {
                        Log::warning('Groq returned empty response', ['data' => $data]);
                        return null;
                    }

                    return [
                        'content' => $text,
                        'model' => $data['model'] ?? $this->model,
                    ];
                }

                // 429 = rate limit. Honor Retry-After (seconds) up to 5s once, then give up.
                if ($response->status() === 429 && $attempt < $maxAttempts) {
                    $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                    if ($retryAfter <= 0) {
                        // Fall back to parsing Groq's "try again in X.YYs" hint from the body.
                        if (preg_match('/try again in ([\d.]+)s/i', $response->body(), $m)) {
                            $retryAfter = (int) ceil((float) $m[1]);
                        }
                    }
                    if ($retryAfter > 0 && $retryAfter <= 5) {
                        Log::info("Groq 429 — sleeping {$retryAfter}s then retrying");
                        sleep($retryAfter);
                        continue;
                    }
                }

                Log::error('Groq API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            } while ($attempt < $maxAttempts);

            return null;
        } catch (\Exception $e) {
            Log::error('Groq exception: ' . $e->getMessage());
            return null;
        } 
    }
}
