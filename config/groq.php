<?php

return [
    'api_key' => env('GROQ_API_KEY', ''),

    // Spare keys, comma-separated, tried in order when the one in use is
    // refused. GROQ_API_KEY stays first — this is only what follows it.
    //
    // Worth knowing what this does and does not buy. Groq counts requests per
    // project but **tokens per organisation**: "Input token (ITPM) and output
    // token (OTPM) rate limits are enforced at the organization level only and
    // cannot be configured per project." So a second key on the same account
    // does NOT carry a second daily token allowance — proved 2026-09-08, where
    // one request on each of two keys moved the same counter 999 → 998. What it
    // does carry is every other reason a key stops working: revoked, deleted,
    // mistyped, or one project's request limit reached while another's is free.
    'api_keys' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GROQ_API_KEYS', ''))
    ))),

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
