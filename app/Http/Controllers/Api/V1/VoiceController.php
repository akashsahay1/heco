<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GroqService;
use App\Services\VoiceAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * The voice assistant behind the app's forms.
 *
 * One turn per call: a recording goes up, and what the member said comes back
 * as fields to fill plus the next question to put to them. Nothing is stored
 * between calls — the app sends what the form already holds each time — so
 * there is no session to expire and nothing to clean up.
 *
 * Unlike the rest of this API it does not forward into AjaxController: the
 * portal has no voice form, so there is no browser behaviour to stay in step
 * with. The thinking lives in VoiceAssistantService, which is where a second
 * caller would find it.
 */
class VoiceController extends Controller
{
    public function __construct(
        private VoiceAssistantService $assistant,
        private GroqService $groq,
    ) {
    }

    /**
     * How the assistant opens: a word from HECO, then the first question.
     *
     * Both come from here rather than the app. The greeting is a setting HCT
     * can reword, and the question has to be worked out from what the form
     * already holds — a member who filled half of it by hand should not be
     * asked about the half they finished.
     */
    public function start(Request $request): JsonResponse
    {
        $provider = Auth::user()?->serviceProvider;
        if (! $provider) {
            return response()->json(['error' => 'This account has no provider profile.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'form' => 'required|in:' . implode(',', $this->assistant->forms()),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Both lines are HCT's own words, and no model is called to produce
        // them: the opening never varies, and a member should not wait on an
        // AI round trip to be said hello to.
        return response()->json([
            'success' => true,
            'greeting' => trim((string) Setting::getValue('voice_greeting', '')) ?: null,
            'reply' => trim((string) Setting::getValue('voice_language_question', '')) ?: null,
            // What the next answer will be taken to mean. Until a language is
            // settled, nothing said is read as an answer about the form.
            'stage' => 'language',
        ]);
    }

    /**
     * The next question, without anyone having to say anything.
     *
     * Used when a member passes over a field. Questions are written down beside
     * the fields rather than composed by a model, so this costs nothing and
     * answers instantly — there is no reason to make someone speak in order to
     * be told what comes next.
     */
    public function next(Request $request): JsonResponse
    {
        $provider = Auth::user()?->serviceProvider;
        if (! $provider) {
            return response()->json(['error' => 'This account has no provider profile.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'form' => 'required|in:' . implode(',', $this->assistant->forms()),
            'known' => 'nullable|array',
            'skipped' => 'nullable|array',
            'skipped.*' => 'string|max:60',
            'language' => 'nullable|in:hi,en',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $known = (array) $request->input('known', []);
        $skipped = (array) $request->input('skipped', []);
        $language = $request->input('language') === 'en' ? 'en' : 'hi';
        $next = $this->assistant->nextField($request->input('form'), $known, $skipped);

        return response()->json([
            'success' => true,
            'fields' => (object) [],
            'reply' => $next === null ? null : $this->assistant->questionFor($request->input('form'), $next, $language, $known),
            'asked' => $next,
            'label' => $next === null ? null : $this->assistant->labelFor($request->input('form'), $next, $known),
            'choices' => $next === null ? null : $this->assistant->choicesFor($request->input('form'), $next, $known),
            'language' => $language,
            'done' => $next === null,
        ]);
    }

    /**
     * Turns a minute of talking into a filled-in form.
     *
     * The recording is read from the request and never written to disk. It is
     * a member's voice: it is worth nothing to us once it is text, and keeping
     * it would mean explaining why we had.
     */
    public function turn(Request $request): JsonResponse
    {
        $provider = Auth::user()?->serviceProvider;
        if (! $provider) {
            return response()->json(['error' => 'This account has no provider profile.'], 403);
        }

        $validator = Validator::make($request->all(), [
            // Judged on what the bytes are, not what the file is called. An
            // m4a is an MP4 container, and a phone's recording of one reports
            // itself as video/mp4 as often as audio/mp4 — so a rule written
            // against the extension turned away every recording the app made.
            // 10 MB is minutes of speech, far more than one turn needs; the
            // ceiling is there so a recorder left running cannot post an hour.
            'audio' => 'required|file|max:10240|mimetypes:'
                . 'audio/mp4,audio/x-m4a,audio/m4a,audio/aac,video/mp4,'
                . 'audio/mpeg,audio/mpga,audio/wav,audio/x-wav,audio/wave,'
                . 'audio/webm,audio/ogg,application/ogg,audio/flac,audio/x-flac',
            'form'  => 'required|in:' . implode(',', $this->assistant->forms()),
            // What the form already holds, as the app has it. Sent every turn
            // rather than kept here: a conversation that lives on the server is
            // a conversation that has to be expired, resumed and cleaned up.
            'known' => 'nullable|array',
            // Absent on the first answer, which is the one that settles it.
            // Sent on every answer after that, so the assistant keeps speaking
            // the language the member asked for even when a reply of theirs is
            // short enough to be mistaken for the other one.
            'language' => 'nullable|in:hi,en',
            // Fields the member has passed over. Sent every turn alongside
            // `known`, for the same reason: nothing about this conversation
            // lives on the server.
            'skipped' => 'nullable|array',
            'skipped.*' => 'string|max:60',
        ], [
            'audio.required' => 'Nothing was recorded.',
            'audio.mimetypes' => 'That is not a recording we can read.',
            'audio.max' => 'That recording is too long. Say a little at a time.',
        ]);

        if ($validator->fails()) {
            // Which format was actually posted, so a phone that records
            // something unexpected can be identified from the log rather than
            // guessed at from a member saying "it does not work".
            if ($request->hasFile('audio')) {
                Log::info('[voice] recording refused', [
                    'mime' => $request->file('audio')->getMimeType(),
                    'name' => $request->file('audio')->getClientOriginalName(),
                    'kb' => (int) round($request->file('audio')->getSize() / 1024),
                ]);
            }

            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        // Groq's free tier allows 8,000 tokens a minute across the whole
        // organisation — not per member. Without a cap here, one member holding
        // a long conversation silently takes the assistant away from everyone
        // else. Twelve turns a minute is faster than anyone actually speaks.
        $limit = RateLimiter::attempt(
            'voice-assistant:' . $provider->id,
            12,
            fn () => true,
        );

        if (! $limit) {
            $wait = RateLimiter::availableIn('voice-assistant:' . $provider->id);

            return response()->json([
                'error' => "You are going faster than the assistant can keep up. Try again in {$wait} seconds, or fill the rest in yourself.",
                'retry_after' => $wait,
            ], 429);
        }

        $file = $request->file('audio');

        // On the first answer nothing is named: that answer IS the choice of
        // language, and it could come in either. From then on the recording is
        // read as the language the member asked for — it is markedly more
        // accurate for not having to guess, about four times faster, and it
        // means what they see written back are their own words rather than a
        // translation they never said.
        //
        // Turning that into English is a separate step, done where the fields
        // are read out of it: a listing is kept in English wherever it came
        // from, so a homestay described in Hindi is still recorded as
        // "Pradeep Homestay".
        $chosen = $request->input('language');
        $heard = $this->groq->transcribe(
            (string) file_get_contents($file->getRealPath()),
            'turn.' . ($file->guessExtension() ?: 'm4a'),
            $chosen
                ? ['language' => $chosen]
                // The first answer is one word, under a second of sound, with
                // no language named and nothing around it to go on. Left to
                // guess, Whisper heard a member say "हिंदी" and wrote it down
                // as Korean. Naming the handful of answers that are expected
                // gives it something to hold on to. It is a bias, not a
                // constraint — anything else said still comes through.
                : ['prompt' => 'Hindi. English. हिंदी. अंग्रेज़ी.'],
        );

        if (! $heard) {
            // Silence, a room too loud to hear over, or the service being down.
            // The member is told plainly, and the form is left exactly as it
            // was — an assistant that cannot hear must not also guess.
            return response()->json([
                'error' => 'That did not come through. Try again, or fill this in yourself.',
                'transcript' => null,
            ], 422);
        }

        // The first answer is not about the form at all: it is which language
        // to hold the conversation in. Only once that is settled does the
        // assistant start asking about rooms and prices.
        $settling = $chosen === null;
        $language = $settling
            ? $this->assistant->languageFrom($heard['text'], $heard['language'])
            : $chosen;

        $result = $this->assistant->turn(
            $request->input('form'),
            (array) $request->input('known', []),
            // Nothing they said while choosing a language is an answer about
            // the form — "Hindi" is not the name of their homestay.
            $settling ? '' : $heard['text'],
            $language,
            (array) $request->input('skipped', []),
        );

        // The assistant heard them but could not be reached to make sense of it
        // — the collective's minute of Groq allowance is spent, or the service
        // is down. Silence would look like the app was broken; this at least
        // says what happened and that waiting will fix it.
        if ($result['unavailable']) {
            return response()->json([
                'error' => 'The assistant is busy just now. Give it a moment and say that again.',
                'transcript' => $heard['text'],
            ], 503);
        }

        return response()->json([
            'success' => true,
            // Shown back to the member so they can see what was heard — the one
            // place a mis-hearing becomes obvious before it reaches the form.
            'transcript' => $heard['text'],
            // Which language the rest of this will be held in. The app sends
            // it back on every turn from here on.
            'language' => $language,
            'fields' => (object) $result['fields'],
            'reply' => $result['reply'],
            'asked' => $result['asked'],
            // The heading the app's own form puts above that box, so the
            // member can see which one is being asked about.
            'label' => $result['label'],
            // What they may choose from, when the field takes one of HCT's
            // own list values. Shown under the question so a member is not
            // guessing at wording the portal will only reject.
            'choices' => $result['choices'],
            // What was heard but could not be used — a room type that is not on
            // HCT's list, a number that was not a number. The member is told,
            // rather than left looking at a box that stayed empty for reasons
            // nobody explained.
            'rejected' => $result['rejected'],
            'done' => $result['done'],
        ]);
    }
}
