<?php

namespace App\Services;

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

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
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

            $response = Http::timeout($this->timeout)->post($url, $payload);

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
            return null;
        } catch (\Exception $e) {
            Log::error('Gemini exception: ' . $e->getMessage());
            return null;
        }
    }
}
