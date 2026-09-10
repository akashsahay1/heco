<?php

return [
    /**
     * Which AI the provider app's voice assistant thinks with: 'groq' or 'openai'.
     *
     * This governs the voice assistant ALONE. The portal's trek-planning chat
     * has its own chain in AjaxController::callAi and is not read from here —
     * the two are separate products and changing one must never move the other.
     *
     * Hearing is a different question and is not settled here. Transcription
     * stays on Groq whatever this says: Whisper's allowance is counted in audio
     * seconds rather than tokens, has never once been exhausted (7,195 of 7,200
     * still free at the moment of writing, on a day the chat ceiling had already
     * been hit), and Groq charges $0.111 an hour against OpenAI's $0.36.
     *
     * 'groq' remains the default so that nothing changes for anyone who has not
     * set a key. Set VOICE_PROVIDER=openai in .env to move it.
     */
    'provider' => env('VOICE_PROVIDER', 'groq'),
];
