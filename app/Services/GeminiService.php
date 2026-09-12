<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key', '');
        $this->model = config('gemini.model', 'gemini-2.5-flash');
        $this->timeout = config('gemini.timeout', 60);
    }

    /**
     * Where a key Google has already refused is remembered.
     *
     * Keyed on the key itself, so putting a working one in `.env` starts it
     * trying again without anybody clearing a cache.
     */
    private const REFUSED = 'gemini.key-refused.';

    public function isAvailable(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        // A key Google answers "API key not valid" to will answer that to
        // every call after it, and this sits in front of the chain: each
        // request paid a round trip to be told the same thing, and the log
        // filled with the same error. Asking once is enough.
        return ! Cache::get(self::REFUSED . md5($this->apiKey), false);
    }

    /**
     * Chat with Gemini API. Accepts Ollama-style messages and converts to Gemini format.
     * Returns ['content' => '...'] to match OllamaService response format.
     */
    public function chat(array $messages, array $options = []): ?array
    {
        if (!$this->isAvailable()) return null;

        try {
            $systemPrompt = null;
            $contents = [];

            foreach ($messages as $msg) {
                $role = $msg['role'] ?? 'user';
                $text = $msg['content'] ?? '';

                if ($role === 'system') {
                    $systemPrompt = $text;
                } elseif ($role === 'assistant' || $role === 'model') {
                    $contents[] = [
                        'role' => 'model',
                        'parts' => [['text' => $text]],
                    ];
                } else {
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $text]],
                    ];
                }
            }

            // Gemini requires at least one content entry
            if (empty($contents)) return null;

            $payload = [
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'maxOutputTokens' => $options['max_tokens'] ?? 2048,
                ],
            ];

            if ($systemPrompt) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => $systemPrompt]],
                ];
            }

            // JSON mode for itinerary generation
            if (!empty($options['format']) && $options['format'] === 'json') {
                $payload['generationConfig']['responseMimeType'] = 'application/json';
            }

            $model = $options['gemini_model'] ?? $this->model;
            $url = $this->baseUrl . '/models/' . $model . ':generateContent?key=' . $this->apiKey;

            $timeout = $options['timeout'] ?? $this->timeout;
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (empty($text)) {
                    Log::warning('Gemini returned empty response', ['data' => $data]);
                    return null;
                }

                return [
                    'content' => $text,
                    'model' => $model,
                ];
            }

            Log::error('Gemini API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Not a bad request, a bad key: it will never come good on its own,
            // so stop asking. Replacing the key in `.env` clears this by
            // itself, because what is remembered is keyed on the key.
            if ($response->status() === 400 && str_contains($response->body(), 'API_KEY_INVALID')) {
                Log::warning('Gemini key refused — not trying it again until it changes');
                Cache::forever(self::REFUSED . md5($this->apiKey), true);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini exception: ' . $e->getMessage());
            return null;
        }
    }
}
