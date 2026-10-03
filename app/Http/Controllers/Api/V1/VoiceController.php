<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GroqService;
use App\Services\VoiceAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
     * Whether this member may be talked through this form at all.
     *
     * An experience is authored by an HLH. Everywhere else that rule was
     * already held — the portal turns a non-host away from the page
     * (SpController::experiences), the app shows the Experiences tab only to a
     * host, and all four of AjaxController's experience handlers go through
     * resolveExperienceAuthor, which answers 403. The voice assistant was the
     * one door left open: it would take an OSP through sixty questions and let
     * them find out at the save button, twenty minutes later, that none of it
     * could be kept.
     *
     * A provider holds a set of types, so an OSP that is also an HLH passes.
     */
    private function refuseForm(\App\Models\ServiceProvider $provider, ?string $form): ?JsonResponse
    {
        if ($form !== 'experience' || $provider->isHost()) {
            return null;
        }

        return response()->json([
            'error' => 'Experiences are authored by homestay and lodge hosts. '
                . 'Your rate card is the form for what you offer.',
        ], 403);
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
            'known' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if ($refusal = $this->refuseForm($provider, $request->input('form'))) {
            return $refusal;
        }

        $form = $request->input('form');
        $known = (array) $request->input('known', []);
        $ahead = $this->assistant->walk($form, $known, [], 'hi');
        $next = $ahead['next'];

        $opening = $this->assistant->greetingFor($provider, $form, Auth::user(), $known);

        // The first question is not sent yet, only carried.
        //
        // Nobody has said a word, so there is nothing to read a language off,
        // and putting the question in both would mean saying everything twice
        // out loud. Instead the member is asked whether they are ready; their
        // answer says which language they speak, and the app then shows the
        // half of the question that belongs to it.

        // HCT's own words, and no model is called for either: the opening
        // never varies, and a member should not wait on an AI round trip to
        // be said hello to.
        return response()->json([
            'success' => true,
            // Put together from the hour, their name and whether they have
            // done this before, rather than read off a setting. Sixty
            // openings, and nothing for anybody to write or maintain.
            'greeting' => $opening[0],
            // Said after the greeting and before anything is asked. Somebody
            // who has just pressed a button and heard a voice needs a moment
            // to answer, and their answer - whatever it is - is what settles
            // which language the rest of this is held in.
            'ready' => $opening[1],
            // Sent for the app that is already on somebody's phone.
            //
            // A build that predates the ready line reads this field and
            // nothing else, so leaving it empty would open the assistant on a
            // blank screen for every provider who has not updated. It is the
            // first question in both tongues, which is exactly what that build
            // expects and puts on screen.
            //
            // The current build ignores it: it waits for the ready line to be
            // answered and asks its first question in the one language that
            // answer was given in.
            'reply' => $next === null ? null : trim(implode("
", array_filter([
                $this->assistant->questionFor($form, $next, 'hi', $known),
                $this->assistant->questionFor($form, $next, 'en', $known),
            ]))),
            'asked' => $next,
            'label' => $next === null ? null : $this->assistant->labelFor($form, $next, $known),
            'choices' => $next === null ? null : $this->assistant->choiceOptionsFor($form, $next, $known, 'hi'),
            'multiple' => $next !== null && $this->assistant->takesSeveral($form, $next, $known),
            'skippable' => $next === null || $this->assistant->skippable($form, $next, $known),
            'guidance' => $ahead['guidance'],
            'passed' => $ahead['passed'],
            'fields' => (object) [],
            'rejected' => [],
            'language' => null,
            'stage' => 'form',
            'done' => $next === null,
        ]);
    }

    /**
     * The next question, without anyone having to say anything.
     *
     * Used when a member passes over a field. Which question comes next is
     * still settled here rather than by a model — there is no reason to make
     * someone speak in order to be told what comes next — but the words it is
     * put in are not, or Skip would be the one place the same sentence came
     * back every time.
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

        if ($refusal = $this->refuseForm($provider, $request->input('form'))) {
            return $refusal;
        }

        $known = (array) $request->input('known', []);
        $skipped = (array) $request->input('skipped', []);
        $language = $request->input('language') === 'en' ? 'en' : 'hi';
        $ahead = $this->assistant->walk($request->input('form'), $known, $skipped, $language);
        $next = $ahead['next'];

        return response()->json([
            'success' => true,
            'fields' => (object) [],
            // Boxes walked past on the way here that cannot be spoken, and
            // what to say about each. See the note in turn().
            'guidance' => $ahead['guidance'],
            'passed' => $ahead['passed'],
            // Said differently each time, like every other question. This is
            // the one place a question went out without a model having seen
            // it — pressing Skip forty times heard the same forty sentences.
            'reply' => $next === null ? null : $this->assistant->phrase(
                $this->assistant->questionFor($request->input('form'), $next, $language, $known),
                $language,
                (string) $this->assistant->labelFor($request->input('form'), $next, $known),
            ),
            'asked' => $next,
            'label' => $next === null ? null : $this->assistant->labelFor($request->input('form'), $next, $known),
            // Pairs rather than words: a member can tap one of these, and
            // what the column holds is not always what the chip reads.
            'choices' => $next === null ? null : $this->assistant->choiceOptionsFor($request->input('form'), $next, $known, $language),
            // Whether tapping one of them is the whole answer, or one of
            // several the member is gathering before they say they are done.
            'multiple' => $next !== null && $this->assistant->takesSeveral($request->input('form'), $next, $known),
            // Whether the app should offer Skip at all. The field that decides
            // the shape of the form cannot be passed over, and offering the
            // button anyway meant pressing it brought the same question
            // straight back with nothing said.
            'skippable' => $next === null || $this->assistant->skippable($request->input('form'), $next, $known),
            'language' => $language,
            'done' => $next === null,
        ]);
    }

    /**
     * A choice tapped rather than spoken.
     *
     * The questions that offer a list already show what may be said. Saying it
     * is the long way round: the recording goes to Whisper, the words go to a
     * model, and the model works out that "रहने की जगह" means `accommodation` —
     * three seconds and two calls to learn something the member had already
     * pointed at. It is also the one path where the mis-hearing happens, and
     * there is nothing to mis-hear in a tap.
     *
     * So nothing is heard here and no model is called. The value is checked
     * against what the field itself offers and written, and the next question
     * comes back exactly as it does after a spoken answer, so the conversation
     * reads as one conversation however each answer arrived.
     */
    public function choose(Request $request): JsonResponse
    {
        $provider = Auth::user()?->serviceProvider;
        if (! $provider) {
            return response()->json(['error' => 'This account has no provider profile.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'form' => 'required|in:' . implode(',', $this->assistant->forms()),
            'field' => 'required|string|max:60',
            // One word, or a list of them. A box that takes several is
            // answered once, when the member has finished choosing: every tap
            // before that is the app's business and costs nothing.
            'value' => 'required',
            'value.*' => 'string|max:200',
            'known' => 'nullable|array',
            'skipped' => 'nullable|array',
            'skipped.*' => 'string|max:60',
            'language' => 'nullable|in:hi,en',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if ($refusal = $this->refuseForm($provider, $request->input('form'))) {
            return $refusal;
        }

        $language = $request->input('language') === 'en' ? 'en' : 'hi';

        $chosen = $request->input('value');
        if (! is_array($chosen) && ! is_string($chosen)) {
            return response()->json(['error' => 'That choice could not be read.'], 422);
        }
        if (is_array($chosen)) {
            $chosen = array_values(array_filter(
                array_map(fn ($one) => is_scalar($one) ? trim((string) $one) : '', $chosen),
                fn ($one) => $one !== '',
            ));

            if ($chosen === []) {
                return response()->json([
                    'error' => $request->input('language') === 'en'
                        ? 'Nothing was chosen.'
                        : 'कुछ चुना नहीं गया।',
                ], 422);
            }
        }

        $result = $this->assistant->chose(
            $request->input('form'),
            (array) $request->input('known', []),
            (array) $request->input('skipped', []),
            (string) $request->input('field'),
            $chosen,
            $language,
        );

        // Either the box does not belong to this form as it now stands, or the
        // value is not one it offers. Both mean the app and the portal have
        // drifted apart, and the member is told plainly rather than watching
        // a tap do nothing.
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'error' => $language === 'hi'
                    ? 'यह विकल्प इस डिब्बे के लिए नहीं है। बोलकर बताइए, या फ़ॉर्म में खुद चुन लीजिए।'
                    : 'That choice does not belong to this box. Say it instead, or pick it on the form yourself.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            // Nothing was heard, so there is nothing to show them as heard.
            // The app puts the chip's own word up as what they answered.
            'transcript' => null,
            'language' => $language,
            'fields' => (object) $result['fields'],
            'reply' => $result['reply'],
            'asked' => $result['asked'],
            'label' => $result['label'],
            'choices' => $result['choices'],
            'multiple' => $result['multiple'],
            'skippable' => $result['skippable'],
            'guidance' => $result['guidance'],
            'passed' => $result['passed'],
            'rejected' => [],
            'note' => null,
            'stage' => 'form',
            'asking_stop' => false,
            'stopped' => false,
            'done' => $result['done'],
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
            // Taken if it is sent and ignored if it is not: a recording
            // answers this question by itself. It is still accepted because
            // the other two doors, Skip and a tapped choice, have no recording
            // to read and go on the last answer instead.
            'language' => 'nullable|in:hi,en',
            // The first answer of all, given to "shall we start?". It is read
            // for its language and for nothing else: "haan" is not the name of
            // anybody's homestay, and putting it in front of a model would
            // spend a call to be told so.
            'ready' => 'nullable|boolean',
            // Their answer to "shall I stop?". Read for yes or no and nothing
            // else, so no model is called to hear one word.
            'confirm_stop' => 'nullable|boolean',
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

        // Before the recording is transcribed, and before anything is counted
        // against them: a form they may not fill is refused whatever they said.
        if ($refusal = $this->refuseForm($provider, $request->input('form'))) {
            return $refusal;
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
        $audio = (string) file_get_contents($file->getRealPath());
        $name = 'turn.' . ($file->guessExtension() ?: 'm4a');

        // Nothing is chosen and nothing is hinted.
        //
        // A member used to be asked, before the form, which language they
        // wanted, and then held to it: an English sentence on a Hindi form was
        // turned away and had to be said again. Both halves of that were
        // wrong. People here put the two languages in one breath, so "double
        // room AC ke saath" is how somebody actually talks, not a mistake; and
        // ten minutes into a form nobody remembers what they picked at the
        // start, so being refused reads as the thing being broken. A member
        // said "I am Pradeep" to a form that had been set to Hindi and was
        // sent away.
        //
        // Nothing is hinted to Whisper either, and that is older: told which
        // language to expect, it echoes the hint back as its answer instead of
        // reporting what it heard (proved 2026-09-09), and English audio read
        // "as Hindi" came back as untouched English labelled Hindi. So it is
        // asked cold, every time.
        // Where the seconds actually go.
        //
        $heard = $this->groq->transcribe($audio, $name);
        $text = trim((string) ($heard['text'] ?? ''));

        // A reading in some third alphabet is not an answer, it is a
        // misreading. This member's plain Hindi has come back as Russian in
        // Cyrillic, as Urdu in Arabic script, and once as the Korean for the
        // word "Hindi". Read it once more, told that it is Hindi, because
        // that is what the speech has been every single time this happened.
        //
        // Transcription is counted in audio seconds, not in the tokens the
        // day's allowance is made of, so the second reading costs nothing
        // that anybody else needs.
        $foreign = fn (string $t) => (bool) preg_match(
            '/[^\p{Latin}\p{Devanagari}\p{Common}\p{Inherited}]/u', $t);

        if ($text !== '' && $foreign($text)) {
            $again = $this->groq->transcribe($audio, $name, ['language' => 'hi']);
            $rescued = trim((string) ($again['text'] ?? ''));

        // Worth knowing that the rescue fired and whether it helped, because a
        // misread alphabet is a fault in the asking. What is deliberately not
        // written down is either reading: a member's own sentences about their
        // own business do not belong in a server log.
            Log::info('[voice] read again for the script', [
                'kept' => $rescued !== '' && ! $foreign($rescued) ? 'the second reading' : 'neither',
                'length' => mb_strlen($text),
            ]);

            if ($rescued !== '' && ! $foreign($rescued)) {
                $heard = $again;
                $text = $rescued;
            } else {
                // Still not a script anybody here writes in. Better to say so
                // than to write it into somebody's listing.
                return response()->json([
                    'error' => 'That did not come through. Say it again, or fill this in yourself.',
                    'transcript' => null,
                ], 422);
            }
        }

        if (! $heard || $text === '') {
            // Silence, a room too loud to hear over, or the service being down.
            // The member is told plainly, and the form is left exactly as it
            // was - an assistant that cannot hear must not also guess.
            return response()->json([
                'error' => 'That did not come through. Try again, or fill this in yourself.',
                'transcript' => null,
            ], 422);
        }

        // Read off what they actually said, not off what they once chose.
        // Whisper's own label is not used for this: it is an opinion, and the
        // script in front of us is evidence.
        $language = $this->assistant->tongueOf($text);

        // One or two words do not change the language of a conversation.
        //
        // A member speaking Hindi answers "Innova", "double room", "AC" - all
        // English words, and every one of them would otherwise turn the next
        // question English on somebody who has been speaking Hindi throughout.
        // It takes a sentence to be taken as a change of language, or an
        // asking, which is the next thing checked.
        $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $before = $request->input('language');

        if ($before !== null && $words <= 3) {
            $language = $before;
        }

        // And a member who asks, in so many words, to be spoken to in the
        // other one. Asked in English - "speak in Hindi, please" - that is an
        // English sentence by every other measure, so nothing but this would
        // catch it.
        $known = (array) $request->input('known', []);
        $form = $request->input('form');
        $asked = $this->assistant->nextField($form, $known, (array) $request->input('skipped', []));
        $asking = $this->assistant->switchRequest($text, $form, $asked, $known);

        if ($asking !== null) {
            // Nothing is written and no model is called: they were talking
            // about the conversation, not about their rooms. The same question
            // comes round again in the language they asked for.
            $ahead = $this->assistant->walk($form, $known, (array) $request->input('skipped', []), $asking);
            $next = $ahead['next'];

            return response()->json([
                'success' => true,
                'transcript' => $text,
                'language' => $asking,
                'fields' => (object) [],
                'reply' => trim($this->assistant->switchedTo($asking) . ' '
                    . ($next === null ? '' : $this->assistant->questionFor($form, $next, $asking, $known))),
                'asked' => $next,
                'label' => $next === null ? null : $this->assistant->labelFor($form, $next, $known),
                'choices' => $next === null ? null : $this->assistant->choiceOptionsFor($form, $next, $known, $asking),
                'multiple' => $next !== null && $this->assistant->takesSeveral($form, $next, $known),
                'skippable' => $next === null || $this->assistant->skippable($form, $next, $known),
                'guidance' => $ahead['guidance'],
                'passed' => $ahead['passed'],
                'rejected' => [],
                'note' => null,
                'stage' => 'form',
                'done' => $next === null,
            ]);
        }

        $standing = $asked;

        // "Shall I stop?" has just been asked, and this is the answer to it.
        //
        // Read here rather than sent to a model: a yes is a yes in both
        // tongues, nothing about the form turns on it, and a member who has
        // said they want to leave should not wait three seconds to be let go.
        if ($request->boolean('confirm_stop')) {
            $yes = $this->assistant->saidYes($text);

            if ($yes === true) {
                return response()->json([
                    'success' => true,
                    'transcript' => $text,
                    'language' => $language,
                    'fields' => (object) [],
                    'reply' => $language === 'hi'
                        ? 'ठीक है, मैं बंद कर रही हूँ। जो भर चुका है वह फ़ॉर्म में है। जब भी लिस्टिंग करनी हो, माइक दबा दीजिए।'
                        : 'All right, I am stopping. What we filled in is on the form. '
                            . 'Whenever you want to carry on with the listing, tap the microphone.',
                    'asked' => $standing,
                    'label' => $standing === null ? null : $this->assistant->labelFor($form, $standing, $known),
                    'choices' => null,
                    'multiple' => false,
                    'skippable' => true,
                    'guidance' => [],
                    'passed' => [],
                    'rejected' => [],
                    'note' => null,
                    'stage' => 'form',
                    'stopped' => true,
                    'done' => false,
                ]);
            }

            // Anything that is not a yes carries on. "नहीं", "carry on", or a
            // word that was neither - all of them mean the form is still open,
            // and the question that was standing is put again.
            return response()->json([
                'success' => true,
                'transcript' => $text,
                'language' => $language,
                'fields' => (object) [],
                'reply' => trim(($language === 'hi' ? 'ठीक है, चलते हैं। ' : 'Right, carrying on. ')
                    . ($standing === null ? '' : (string) $this->assistant->questionFor($form, $standing, $language, $known))),
                'asked' => $standing,
                'label' => $standing === null ? null : $this->assistant->labelFor($form, $standing, $known),
                'choices' => $standing === null ? null : $this->assistant->choiceOptionsFor($form, $standing, $known, $language),
                'multiple' => $standing !== null && $this->assistant->takesSeveral($form, $standing, $known),
                'skippable' => $standing === null || $this->assistant->skippable($form, $standing, $known),
                'guidance' => [],
                'passed' => [],
                'rejected' => [],
                'note' => null,
                'stage' => 'form',
                'stopped' => false,
                'done' => false,
            ]);
        }

        // A member saying they will do this another time, or asking it to
        // stop. Nothing is written and no model is called: they are asked
        // whether to stop, and the answer to that comes back with
        // confirm_stop above.
        if ($this->assistant->stopRequest($text, $form, $standing, $known)) {
            return response()->json([
                'success' => true,
                'transcript' => $text,
                'language' => $language,
                'fields' => (object) [],
                'reply' => $language === 'hi'
                    ? 'क्या मैं अभी बंद कर दूँ? हाँ कहिए तो बंद कर देती हूँ।'
                    : 'Shall I stop here? Say yes and I will.',
                'asked' => $standing,
                'label' => $standing === null ? null : $this->assistant->labelFor($form, $standing, $known),
                'choices' => null,
                'multiple' => false,
                'skippable' => true,
                'guidance' => [],
                'passed' => [],
                'rejected' => [],
                'note' => null,
                'stage' => 'form',
                'asking_stop' => true,
                'done' => false,
            ]);
        }

        // They were answering "shall we start?", so this settles the language
        // and nothing else. No model is called and nothing is written: the
        // first question simply comes back in the tongue they used.
        if ($request->boolean('ready')) {
            $ahead = $this->assistant->walk($form, $known, (array) $request->input('skipped', []), $language);
            $next = $ahead['next'];

            return response()->json([
                'success' => true,
                'transcript' => $text,
                'language' => $language,
                'fields' => (object) [],
                'reply' => $next === null ? null : $this->assistant->questionFor($form, $next, $language, $known),
                'asked' => $next,
                'label' => $next === null ? null : $this->assistant->labelFor($form, $next, $known),
                'choices' => $next === null ? null : $this->assistant->choiceOptionsFor($form, $next, $known, $language),
                'multiple' => $next !== null && $this->assistant->takesSeveral($form, $next, $known),
                'skippable' => $next === null || $this->assistant->skippable($form, $next, $known),
                'guidance' => $ahead['guidance'],
                'passed' => $ahead['passed'],
                'rejected' => [],
                'note' => null,
                'stage' => 'form',
                'done' => $next === null,
            ]);
        }

        $result = $this->assistant->turn(
            $request->input('form'),
            (array) $request->input('known', []),
            $text,
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
            // guessing at wording the portal will only reject, and tappable,
            // which is why each one carries the value as well as the word.
            'choices' => $result['asked'] === null ? null : $this->assistant->choiceOptionsFor(
                $request->input('form'),
                $result['asked'],
                $result['fields'] + (array) $request->input('known', []),
                $language,
            ),
            'multiple' => $result['asked'] !== null && $this->assistant->takesSeveral(
                $request->input('form'),
                $result['asked'],
                $result['fields'] + (array) $request->input('known', []),
            ),
            'skippable' => $result['asked'] === null
                || $this->assistant->skippable($request->input('form'), $result['asked'], (array) $request->input('known', [])),
            // What was heard but could not be used — a room type that is not on
            // HCT's list, a number that was not a number. The member is told,
            // rather than left looking at a box that stayed empty for reasons
            // nobody explained.
            'rejected' => $result['rejected'],
            // Why nothing was filled in, when nothing was filled in and
            // nothing was turned away either — the answer was about something
            // else. Null on an ordinary turn.
            'note' => $result['note'] ?? null,
            // Boxes the conversation walked past because no one can fill them
            // by talking — photographs, a map pin, the table of extras — and
            // what to tell the member about each. `passed` names them so the
            // app can put them behind it: nothing is stored here, so a box not
            // marked as passed would be announced again every turn.
            'guidance' => $result['guidance'] ?? [],
            'passed' => $result['passed'] ?? [],
            // A box the member asked to go back to. It has been emptied, and
            // the app has to stop counting it among the ones passed over or it
            // will be stepped straight past again.
            'reopened' => $result['reopened'] ?? null,
            'done' => $result['done'],
        ]);
    }
}
