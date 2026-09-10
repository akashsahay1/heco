<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI, for the provider app's voice assistant.
 *
 * Deliberately its own class rather than a branch inside GroqService. That one
 * is shared with the portal's trek-planning chat (AjaxController::callAi), and
 * the standing rule on this project is that the two AIs never reach into each
 * other. A separate class means the portal cannot be changed by accident.
 *
 * The wire format is the same one GroqService speaks — Groq copied it — so this
 * is mostly a different host and a different key. Three things do differ, and
 * each cost somebody an afternoon somewhere:
 *
 *  - `max_tokens` is refused outright by the GPT-5 family: "Unsupported
 *    parameter: 'max_tokens' is not supported with this model. Use
 *    'max_completion_tokens' instead." Sent wrongly, every single call 400s and
 *    the assistant simply goes quiet, which looks exactly like a broken feature.
 *  - Some models in that family accept only the default temperature.
 *  - `reasoning_effort` is understood, and is worth keeping: reading one
 *    sentence to fill one box is not work that wants deliberation, and thinking
 *    is charged to the same budget as the answer.
 *
 * The last two are handled by asking, failing, and asking again without the
 * parameter the API named — rather than by guessing today which model allows
 * what, and being wrong in six weeks when the lineup moves.
 */
class OpenAiService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('openai.api_key', '');
        $this->model = (string) config('openai.model', 'gpt-5.6-luna');
        $this->timeout = (int) config('openai.timeout', 60);
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * One exchange. Returns ['content' => ..., 'model' => ...] or null, which is
     * the shape GroqService returns and everything downstream already handles.
     */
    public function chat(array $messages, array $options = []): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $payload = [
            'model' => $options['openai_model'] ?? $options['groq_model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_completion_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if (($options['format'] ?? null) === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        if (! empty($options['reasoning_effort'])) {
            $payload['reasoning_effort'] = $options['reasoning_effort'];
        }

        // A model this one does not know about is a model whose parameter rules
        // this one does not know either. Rather than keep a table of which
        // family allows what — which would be wrong within the month — an
        // unsupported parameter is dropped when the API says which one it is,
        // and the call is made again. At most a few times, and only ever
        // narrowing the request.
        $timeout = $options['timeout'] ?? $this->timeout;

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($this->baseUrl . '/chat/completions', $payload);
            } catch (\Exception $e) {
                Log::error('OpenAI exception: ' . $e->getMessage());

                return null;
            }

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';

                if ($text === '') {
                    // Reasoning models spend the budget thinking and hand back
                    // an empty message when it runs out. Worth its own line in
                    // the log: it is not an outage and not a refusal.
                    Log::warning('OpenAI returned an empty message', [
                        'model' => $data['model'] ?? $payload['model'],
                        'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
                    ]);

                    return null;
                }

                // Cut off rather than finished. Worth saying, because it looks
                // like nothing from the outside: the reply arrives, reads
                // perfectly well, and simply stops in the middle of a word.
                // A Hindi answer ended two syllables into "example" this way.
                // Reasoning is charged to the same budget as the answer, so a
                // ceiling that was ample on a plain model is not on this one.
                if (($data['choices'][0]['finish_reason'] ?? null) === 'length') {
                    Log::warning('OpenAI ran out of room mid-answer', [
                        'model' => $data['model'] ?? $payload['model'],
                        'max_completion_tokens' => $payload['max_completion_tokens'] ?? null,
                        'reasoning_tokens' => $data['usage']['completion_tokens_details']['reasoning_tokens'] ?? null,
                        'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
                    ]);
                }

                return [
                    'content' => $text,
                    'model' => $data['model'] ?? $payload['model'],
                ];
            }

            $body = $response->body();

            // "Unsupported parameter: 'x'" and "Unsupported value: 'x'" both
            // name the offender; drop it and go again.
            if ($response->status() === 400
                && preg_match("/Unsupported (?:parameter|value): '([a-z_]+)'/i", $body, $m)
                && array_key_exists($m[1], $payload)
                && ! in_array($m[1], ['model', 'messages'], true)) {
                Log::info("OpenAI does not take '{$m[1]}' on this model — asking again without it");
                unset($payload[$m[1]]);

                continue;
            }

            // Not every 429 is a rate limit. An empty balance arrives with the
            // same status and is not a wait at all — it is a standing state
            // until somebody adds credit. Retrying it three times took 12
            // seconds to reach the same answer, and a member on a phone is
            // standing there for all twelve.
            if ($response->status() === 429 && str_contains($body, 'insufficient_quota')) {
                Log::error('OpenAI has no credit on this account — nothing to wait for');

                return null;
            }

            // A real rate limit, which a paid account meets only in bursts.
            if ($response->status() === 429 && $attempt < 4) {
                $wait = (int) ($response->header('retry-after') ?: 2);
                if ($wait > 0 && $wait <= 5) {
                    Log::info("OpenAI 429 — waiting {$wait}s");
                    sleep($wait);

                    continue;
                }
            }

            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => mb_substr($body, 0, 500),
            ]);

            return null;
        }

        return null;
    }
}
