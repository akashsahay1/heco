<?php

return [
    'api_key' => env('GROQ_API_KEY', ''),
    'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    'timeout' => env('GROQ_TIMEOUT', 60),
];
