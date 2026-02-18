<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->host = config('ollama.host');
        $this->model = config('ollama.model');
        $this->timeout = config('ollama.timeout');
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->host . '/api/tags');
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Ollama not available: ' . $e->getMessage());
            return false;
        }
    }

    public function chat(array $messages, ?string $model = null, array $options = []): ?array
    {
        try {
            $payload = [
                'model' => $model ?? $this->model,
                'messages' => $messages,
                'stream' => false,
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['max_tokens'] ?? 4096,
                ],
            ];

            if (isset($options['format'])) {
                $payload['format'] = $options['format'];
            }

            $response = Http::timeout($this->timeout)
                ->post($this->host . '/api/chat', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'content' => $data['message']['content'] ?? '',
                    'model' => $data['model'] ?? $this->model,
                    'total_duration' => $data['total_duration'] ?? null,
                ];
            }

            Log::error('Ollama chat failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Ollama chat exception: ' . $e->getMessage());
            return null;
        }
    }

    public function generate(string $prompt, ?string $model = null, array $options = []): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post($this->host . '/api/generate', [
                    'model' => $model ?? $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $options['temperature'] ?? 0.7,
                        'num_predict' => $options['max_tokens'] ?? 4096,
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('response');
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama generate exception: ' . $e->getMessage());
            return null;
        }
    }
}
