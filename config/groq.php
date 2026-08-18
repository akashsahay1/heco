<?php

return [
    'api_key' => env('GROQ_API_KEY', ''),

    // Which models this key can actually call is not what /models lists: that
    // endpoint advertises the whole catalogue, and the key 404s on most of it.
    // Both defaults here were verified against the project's own key on
    // 2026-08-17; llama-3.3-70b-versatile and llama-3.1-8b-instant, which this
    // used to name, answer 404 and left the Groq link of callAi() dead.
    'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),

    // Speech to text. Groq exposes Whisper on the same key and base URL, so a
    // recording costs no extra account and no extra secret.
    'transcribe_model' => env('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3'),

    'timeout' => env('GROQ_TIMEOUT', 60),
];
