<?php

return [
    'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    'model' => env('OLLAMA_MODEL', 'mistral'),
    'timeout' => env('OLLAMA_TIMEOUT', 120),
];
