<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';

    /** Every key this account has, in the order they are tried. */
    protected array $keys = [];

    /**
     * How long a refused key is left alone.
     *
     * Groq's daily ceiling is a rolling window rather than a calendar day —
     * capacity trickles back through the day rather than arriving at midnight —
     * so a key that is spent now may well answer in twenty minutes. Long enough
     * not to re-ask on every turn; short enough to notice the recovery.
     */
    protected const RESTED = 900;

    public function __construct()
    {
        $this->keys = array_values(array_unique(array_filter(array_merge(
            [(string) config('groq.api_key', '')],
            (array) config('groq.api_keys', []),
        ))));
        $this->apiKey = $this->keys[0] ?? '';
        $this->model = config('groq.model', 'llama-3.3-70b-versatile');
        $this->timeout = config('groq.timeout', 60);
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * The keys worth trying, spent ones last.
     *
     * Not "spent ones removed": if every key has been refused the caller still
     * gets an attempt rather than a silence, and the voice assistant falling
     * silent is the one failure a member cannot tell from a broken feature.
     */
    protected function keysToTry(): array
    {
        $fresh = [];
        $tired = [];
        foreach ($this->keys as $key) {
            if (Cache::has('groq_key_spent:' . md5($key))) {
                $tired[] = $key;
            } else {
                $fresh[] = $key;
            }
        }

        return array_merge($fresh, $tired) ?: [$this->apiKey];
    }

    /** Put a key aside, by its hash — the key itself never reaches a log. */
    protected function rest(string $key, string $why): void
    {
        Cache::put('groq_key_spent:' . md5($key), true, self::RESTED);
        Log::warning('Groq key set aside', [
            'key' => '…' . substr($key, -4),
            'why' => $why,
            'for_seconds' => self::RESTED,
        ]);
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

            // The first key not currently set aside. Transcription has its own
            // allowance — audio seconds, not tokens — so a key out of tokens for
            // the day still hears perfectly well; but a revoked one does not,
            // and that is the case worth stepping past. One attempt, no
            // rotation: an unheard recording is gone, and a member repeating
            // themselves through several keys is worse than being asked again.
            $response = Http::timeout($options['timeout'] ?? $this->timeout)
                ->withHeaders(['Authorization' => 'Bearer ' . ($this->keysToTry()[0] ?? $this->apiKey)])
                ->attach('file', $bytes, $filename)
                ->post($this->baseUrl . '/audio/transcriptions', $payload);

            if (!$response->successful()) {
                Log::error('Groq transcription error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $text = $this->spokenPartOf($response->json());
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
     * The part of a transcript somebody actually said.
     *
     * Whisper does not return silence as silence. Given nothing to hear it
     * invents fluent, plausible speech, and the longer the quiet the more of
     * it there is — a member who tapped the microphone and was called away
     * came back to a paragraph of Hindi they never spoke, offered to the form
     * as their answer.
     *
     * It does, however, say so. Every segment carries how sure it is that
     * nothing was spoken in it, so the invention is thrown away here rather
     * than being guarded against by cutting the member off mid-sentence. They
     * may hold the microphone as long as they like.
     *
     * A transcript with no segments at all is taken at its word: better a
     * stray sentence than dropping a real answer over a missing field.
     */
    private function spokenPartOf(?array $body): string
    {
        $segments = $body['segments'] ?? null;
        if (! is_array($segments) || $segments === []) {
            return trim((string) ($body['text'] ?? ''));
        }

        $spoken = [];
        $silent = 0;
        $kept = [];
        $dropped = [];

        foreach ($segments as $segment) {
            $quiet = (float) ($segment['no_speech_prob'] ?? 0.0);
            $sure = (float) ($segment['avg_logprob'] ?? 0.0);
            $said = trim((string) ($segment['text'] ?? ''));

            // Whisper's own threshold for "there was no speech here", and the
            // marks of something it made up rather than heard.
            //
            // The numbers alone cannot tell the two apart. A fan came back as
            // "Thank you for watching." at no_speech 0.13 and confidence -0.52,
            // and a member saying how many seats their car has, four times
            // over, came back at no_speech 0.71 to 0.84 and still sure of the
            // words (-0.37 to -0.8): every one of those was thrown away. So the
            // numbers decide only as Whisper itself does, silence being BOTH
            // likely no speech AND words it is unsure of, and the rest is
            // caught by what invention looks like: a line from the end of a
            // video, one word said over and over, or no letters at all.
            // Somebody talking across the room is the app's to leave out.
            if (($quiet > 0.6 && $sure < -1.0) || $this->madeUp($said)) {
                $silent++;
                $dropped[] = [$quiet, $sure];
                continue;
            }
            $spoken[] = $said;
            $kept[] = [$quiet, $sure];
        }

        // Numbers only, never the words: what real phones send, so the line
        // above can be set from evidence rather than from one test.
        Log::info('Groq transcription: heard', [
            'segments' => count($segments),
            'discarded' => $silent,
            'kept_no_speech_max' => $kept ? round(max(array_column($kept, 0)), 2) : null,
            'kept_logprob_min' => $kept ? round(min(array_column($kept, 1)), 2) : null,
            'dropped' => array_map(fn ($d) => [round($d[0], 2), round($d[1], 2)], $dropped),
        ]);

        return trim(implode(' ', array_filter($spoken)));
    }

    /**
     * What Whisper says when there was nothing to hear.
     *
     * It was trained on videos, so an empty room comes back as the end of
     * one, and noise read as Hindi comes back as a single word twice over
     * ("प्रभाव प्रभाव"). Nobody filling in a listing says either.
     */
    private function madeUp(string $said): bool
    {
        // Nothing but marks ("...", a music note) is invention. A number is
        // not: asked what year the car is, "2018" is the whole answer, in
        // English and in Hindi alike, and treating a line with no letters as
        // made up threw every such answer away.
        if (! preg_match('/[\p{L}\p{N}]/u', $said)) {
            return true;
        }

        $plain = trim(preg_replace('/[^\p{L}\p{M}\s]+/u', ' ', mb_strtolower($said)));

        if (preg_match('/^(thank you|thanks)( so much| very much)? for watching|please subscribe|like and subscribe|see you (in the )?next (time|video)|subtitles by|amara\s*org/u', $plain)) {
            return true;
        }

        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) >= 2 && count(array_unique($words)) === 1;
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

            // Each key in turn, spent ones last. A key is set aside when it is
            // out of tokens for the day or no longer valid — not when the
            // MINUTE's allowance is reached, which is a wait, not an ending,
            // and which every key on the account shares anyway.
            foreach ($this->keysToTry() as $key) {
                $attempt = 0;

                do {
                    $attempt++;
                    $response = Http::timeout($timeout)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $key,
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

                    // Worth moving on to the next key, or not:
                    //   401 — revoked, deleted or mistyped. This key is finished.
                    //   429 on the DAY's tokens — nothing more from it for a while.
                    //   429 on the MINUTE's — every key shares that ceiling, so
                    //     moving on would only spend a second key on the same wall.
                    // The day's tokens are counted per organisation, not per
                    // key, so when one key is out they all are. Trying the rest
                    // spends nothing but it does make the member wait three
                    // times as long for the same silence, which is what
                    // happened on 2026-09-09: three "out of tokens for the day"
                    // in a row inside a single turn. Set them all aside and
                    // stop.
                    if ($response->status() === 429 && str_contains($response->body(), 'tokens per day')) {
                        foreach ($this->keys as $spent) {
                            $this->rest($spent, 'the day is spent for this model, on every key');
                        }
                        return null;
                    }

                    // A key that is no longer valid is the one case another key
                    // really does answer.
                    if ($response->status() === 401 && count($this->keys) > 1) {
                        $this->rest($key, 'invalid');
                        break;   // out of the retry loop, on to the next key
                    }

                    return null;
                } while ($attempt < $maxAttempts);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Groq exception: ' . $e->getMessage());
            return null;
        } 
    }
}
