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
     * Turn a recording into text.
     *
     * Same key and same base URL as chat() — Groq serves Whisper alongside its
     * text models, so speech costs no second account and no second secret.
     *
     * Returns ['text' => '...', 'language' => 'hi', 'duration' => 18.5] or null.
     * Null covers every failure: the caller decides what to tell the member,
     * and a member who cannot be transcribed must still be able to type.
     *
     * @param string $bytes    The audio itself, not a path — it arrives in a
     *                         request and is never written to disk.
     * @param string $filename Only for the extension; Groq reads the format
     *                         from it. m4a, mp3, wav, ogg, webm and flac work.
     */
    public function transcribe(string $bytes, string $filename, array $options = []): ?array
    {
        if (!$this->isAvailable()) return null;

        try {
            $payload = [
                ['name' => 'model', 'contents' => $options['model'] ?? config('groq.transcribe_model')],
                // verbose_json also reports which language it heard, which is
                // worth logging: it is the first thing to look at when a
                // transcript comes back as nonsense.
                ['name' => 'response_format', 'contents' => 'verbose_json'],
            ];
            // Naming the language makes Whisper markedly more accurate, but
            // guessing it wrong is worse than not saying — so it is only sent
            // when the caller actually knows.
            if (!empty($options['language'])) {
                $payload[] = ['name' => 'language', 'contents' => $options['language']];
            }
            if (!empty($options['prompt'])) {
                $payload[] = ['name' => 'prompt', 'contents' => $options['prompt']];
            }

            $response = Http::timeout($options['timeout'] ?? $this->timeout)
                ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->attach('file', $bytes, $filename)
                ->post($this->baseUrl . '/audio/transcriptions', $payload);

            if (!$response->successful()) {
                Log::error('Groq transcription error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $text = trim((string) $response->json('text'));
            if ($text === '') {
                // Silence, or a room too loud to hear over. Not an error, but
                // there is nothing to hand on either.
                return null;
            }

            return [
                'text' => $text,
                'language' => $response->json('language'),
                'duration' => $response->json('duration'),
            ];
        } catch (\Exception $e) {
            Log::error('Groq transcription exception: ' . $e->getMessage());
            return null;
        }
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

            // Groq's gpt-oss models think before they answer, and that thinking
            // is spent out of max_tokens: left to itself the model reasoned its
            // way through the whole budget and returned an empty message. A
            // caller doing something mechanical — reading one sentence, filling
            // one field — says 'low' and gets its answer instead.
            if (!empty($options['reasoning_effort'])) {
                $payload['reasoning_effort'] = $options['reasoning_effort'];
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
