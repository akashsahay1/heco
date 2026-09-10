<?php

return [
    'api_key' => env('OPENAI_API_KEY', ''),

    // GPT-5.6 Luna is the one OpenAI itself points at this kind of work: its
    // model page calls it "optimized for low-latency inference, multilingual
    // workloads" and names voice agents among its uses, and the 5.6 family's
    // Indian-language handling is the reason for choosing it over anything
    // cheaper. Latency matters here in a way it does not elsewhere — a member
    // is standing there, mid-sentence, waiting to be asked the next thing.
    'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),

    'timeout' => env('OPENAI_TIMEOUT', 60),
];
