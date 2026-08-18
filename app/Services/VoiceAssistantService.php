<?php

namespace App\Services;

use App\Models\SystemList;
use Illuminate\Support\Facades\Log;

/**
 * The conversation behind the app's voice assistant.
 *
 * A member who has just been approved is shown a form with sixty boxes and no
 * idea what belongs in them. This asks instead: one short question at a time,
 * in the language they answered in, filling the form as it goes.
 *
 * Everything happens here rather than in the app. The app records, sends the
 * audio, and writes back whatever comes out — it holds no prompt, no key and
 * no notion of what a rate card contains, so a change to the form is a change
 * to this file alone.
 *
 * No conversation is stored. Each turn arrives carrying what is already known,
 * which keeps the exchange stateless and — more to the point — keeps it small:
 * Groq's free tier allows 8,000 tokens a minute across the whole organisation,
 * so a turn that resent every option list ran the account dry in three
 * exchanges. Only the list for the field being asked is sent.
 */
class VoiceAssistantService
{
    /**
     * How many unanswered fields to carry option lists for.
     *
     * The lists are what cost: sending every one of them on every turn spent
     * 4,700 tokens an exchange and emptied the minute's allowance in three.
     * The names and hints alongside them are a few words each, so those are
     * not rationed — see the note on the two windows in turn().
     */
    private const LISTS_AHEAD = 6;

    /**
     * The forms this can fill, and what each field is.
     *
     * `list` names the SystemList the value must be copied from, `only` a fixed
     * set the database enum already fixes, and `ask` is the hint the model
     * turns into a question. Order is the order they are asked, and it follows
     * how a person describes their place rather than how the table stores it.
     *
     * @return array<string,array<string,mixed>>
     */
    public function schema(string $form, array $known = []): array
    {
        return match ($form) {
            'rate' => $this->rateSchema($known),
            'experience' => $this->experienceSchema($known),
            default => [],
        };
    }

    /** Every form this understands, for the app to ask about. */
    public function forms(): array
    {
        return ['rate', 'experience'];
    }

    /** What a rate card asks, which depends on the kind of service it is. */
    private function rateSchema(array $known): array
    {
        $common = [
            'service_type' => [
                'label' => 'Service type',
                'ask' => 'whether they are offering a place to stay, a vehicle, a guide, an activity, or something they rent out',
                'q' => [
                    'hi' => 'आप क्या दे रहे हैं — रहने की जगह, गाड़ी, गाइड, कोई गतिविधि, या किराये पर कुछ सामान?',
                    'en' => 'What are you offering — a place to stay, a vehicle, a guide, an activity, or something you rent out?',
                ],
                'type' => 'string',
                'only' => ['accommodation', 'transport', 'guide', 'activity', 'rental'],
            ],
        ];

        // Until they have said what they offer there is nothing sensible to ask
        // next: every field below belongs to one kind of service.
        return $common + match ($known['service_type'] ?? null) {
            'accommodation' => [
                'category' => ['label' => 'Property name', 'ask' => 'what their place is called', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string'],
                'comfort_tier' => ['label' => 'Comfort tier', 'ask' => 'what sort of place it is', 'q' => ['hi' => 'यह किस तरह की जगह है?', 'en' => 'What sort of place is it?'], 'type' => 'string', 'list' => 'accommodation_category'],
                'room_category' => ['label' => 'Room category', 'ask' => 'what kind of room they are pricing', 'q' => ['hi' => 'आप किस तरह के कमरे का दाम बता रहे हैं?', 'en' => 'Which kind of room are you pricing?'], 'type' => 'string', 'list' => 'room_category'],
                'total_rooms' => ['label' => 'Total rooms', 'ask' => 'how many rooms of that kind they have', 'q' => ['hi' => 'ऐसे कितने कमरे हैं आपके पास?', 'en' => 'How many such rooms do you have?'], 'type' => 'int'],
                'default_occupancy' => ['label' => 'Default occupancy', 'ask' => 'how many people sleep in one such room', 'q' => ['hi' => 'एक कमरे में कितने लोग रह सकते हैं?', 'en' => 'How many people stay in one such room?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'price' => ['label' => 'Rate per night (Rs)', 'ask' => 'what one room costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that price is per night or per person', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'meal_plan' => ['label' => 'Meal plan', 'ask' => 'which meals are included in that price', 'q' => ['hi' => 'इस दाम में कौन सा खाना शामिल है?', 'en' => 'Which meals are included in that price?'], 'type' => 'string', 'list' => 'meal_plan'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'transport' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this vehicle on their rate card', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string'],
                'vehicle_type' => ['label' => 'Vehicle type', 'ask' => 'what kind of vehicle it is', 'q' => ['hi' => 'गाड़ी कौन सी है?', 'en' => 'What kind of vehicle is it?'], 'type' => 'string', 'list' => 'vehicle_type'],
                'vehicle_capacity' => ['label' => 'Seating capacity', 'ask' => 'how many passengers it seats', 'q' => ['hi' => 'इसमें कितने लोग बैठ सकते हैं?', 'en' => 'How many passengers does it seat?'], 'type' => 'int'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that is per day, per trip or per kilometre', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'driver_allowance' => ['label' => 'Driver allowance (Rs/day)', 'ask' => 'what the driver is paid on top, if anything', 'q' => ['hi' => 'ड्राइवर का अलग से कुछ खर्च है क्या?', 'en' => 'Is there anything paid to the driver on top?'], 'type' => 'number'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'guide' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this guiding service', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what they guide — birds, forest, culture, and so on', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'price' => ['label' => 'Rate per day (Rs)', 'ask' => 'what they charge for a day', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that is per day or per person', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'activity' => [
                'category' => ['label' => 'Service name', 'ask' => 'what the activity is called', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what the activity involves', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that price is per person or per group', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'min_group' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
                'max_group' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'rental' => [
                'rental_item' => ['label' => 'Item on rent', 'ask' => 'what they rent out', 'q' => ['hi' => 'आप किराये पर क्या देते हैं?', 'en' => 'What do you rent out?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs to rent', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that is per day or per person', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'security_deposit' => ['label' => 'Security deposit (Rs)', 'ask' => 'what deposit they hold, if any', 'q' => ['hi' => 'कितनी रकम जमानत के तौर पर रखते हैं?', 'en' => 'What deposit do you hold?'], 'type' => 'number'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            default => [],
        };
    }

    /**
     * What an experience asks.
     *
     * Photos and the day-by-day plan are deliberately absent: neither can be
     * spoken, and both are better done by hand once the rest is filled in.
     */
    private function experienceSchema(array $known): array
    {
        $opening = [
            'name' => ['label' => 'Name', 'ask' => 'what the experience is called', 'q' => ['hi' => 'इस अनुभव का नाम क्या है?', 'en' => 'What is this experience called?'], 'type' => 'string'],
            'type' => ['label' => 'Type', 'ask' => 'what sort of experience it is', 'q' => ['hi' => 'यह किस तरह का अनुभव है?', 'en' => 'What sort of experience is it?'], 'type' => 'string', 'list' => 'experience_type'],
            'category' => ['label' => 'What kind of experience is this?', 'ask' => 'which category it belongs to', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string', 'list' => 'experience_category'],
            'short_description' => ['label' => 'Short description', 'ask' => 'a sentence or two describing it to a traveller', 'q' => ['hi' => 'एक-दो लाइन में बताइए, यात्री को इसमें क्या मिलेगा?', 'en' => 'In a line or two, what does a traveller get from it?'], 'type' => 'string'],
            'duration_type' => [
                'label' => 'Duration type',
                'ask' => 'whether it takes a few hours, a whole day, or several days',
                'q' => [
                    'hi' => 'इसमें कितना समय लगता है — कुछ घंटे, पूरा दिन, या कई दिन?',
                    'en' => 'How long does it take — a few hours, a whole day, or several days?',
                ],
                'type' => 'string',
                'only' => ['less_than_day', 'single_day', 'multi_day'],
            ],
        ];

        // What the duration means in numbers depends on which duration it is,
        // so the follow-up appears only once they have said which.
        $duration = match ($known['duration_type'] ?? null) {
            'less_than_day' => ['duration_hours' => ['label' => 'Duration (hours)', 'ask' => 'how many hours it takes', 'q' => ['hi' => 'कितने घंटे लगते हैं?', 'en' => 'How many hours does it take?'], 'type' => 'number']],
            'multi_day' => [
                'duration_days' => ['label' => 'Days', 'ask' => 'how many days it runs', 'q' => ['hi' => 'कितने दिन चलता है?', 'en' => 'How many days does it run?'], 'type' => 'int'],
                'duration_nights' => ['label' => 'Nights', 'ask' => 'how many nights that includes', 'q' => ['hi' => 'इसमें कितनी रातें आती हैं?', 'en' => 'How many nights does that include?'], 'type' => 'int'],
            ],
            default => [],
        };

        return $opening + $duration + [
            'group_size_min' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोग होने चाहिए?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
            'group_size_max' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोग आ सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
            'base_cost_per_person' => ['label' => 'Price pp (Rs)', 'ask' => 'what one person pays', 'q' => ['hi' => 'एक व्यक्ति का कितना लगता है?', 'en' => 'What does one person pay?'], 'type' => 'number'],
            'includes_guide' => ['label' => 'Guide', 'ask' => 'whether a guide comes with it', 'q' => ['hi' => 'इसके साथ गाइड जाता है क्या?', 'en' => 'Does a guide go along with it?'], 'type' => 'bool'],
            'includes_transport' => ['label' => 'Transport', 'ask' => 'whether transport is included', 'q' => ['hi' => 'आने-जाने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is transport included?'], 'type' => 'bool'],
            'includes_accommodation' => ['label' => 'Accommodation', 'ask' => 'whether a place to stay is included', 'q' => ['hi' => 'रहने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is a place to stay included?'], 'type' => 'bool'],
            'difficulty_level' => [
                'label' => 'Difficulty level',
                'ask' => 'how hard it is physically',
                'q' => [
                    'hi' => 'यह शरीर से कितना मुश्किल है?',
                    'en' => 'How hard is it physically?',
                ],
                'type' => 'string',
                'only' => ['easy', 'moderate', 'challenging'],
            ],
            'traveller_bring_list' => ['label' => 'What travellers should bring', 'ask' => 'what a traveller should bring', 'q' => ['hi' => 'यात्री को अपने साथ क्या लाना चाहिए?', 'en' => 'What should a traveller bring with them?'], 'type' => 'string'],
            'long_description' => ['label' => 'Long description', 'ask' => 'the fuller story of the experience', 'q' => ['hi' => 'इस अनुभव की पूरी बात बताइए।', 'en' => 'Tell me the fuller story of this experience.'], 'type' => 'string'],
        ];
    }

    /**
     * One exchange: what they said goes in, the form comes back fuller and a
     * question comes back with it.
     *
     * @param  array<string,mixed>  $known  What the form already holds. The app
     *         sends it every turn — nothing is kept here between calls.
     * @param  string  $language  Which tongue to open in. Once a member has
     *         said something the model follows them; this only decides the
     *         very first question, when there is nothing yet to follow.
     * @param  bool  $reread  Internal. False on the second look at the same
     *         sentence, so the re-read cannot itself trigger another.
     * @return array{fields:array<string,mixed>,reply:?string,asked:?string,label:?string,choices:?array,done:bool,rejected:array<int,string>,unavailable:bool}
     */
    public function turn(
        string $form,
        array $known,
        string $said,
        string $language = 'hi',
        bool $reread = true,
    ): array
    {
        $asked = $this->nextField($form, $known);

        // Nothing said means nothing to read: hand back the question for
        // wherever the form has got to, without troubling the model at all.
        if ($asked !== null && trim($said) === '') {
            return [
                'fields' => [],
                'reply' => $this->questionFor($form, $asked, $language, $known),
                'asked' => $asked,
                'label' => $this->labelFor($form, $asked, $known),
                'choices' => $this->choicesFor($form, $asked, $known),
                'done' => false,
                'rejected' => [],
                'unavailable' => false,
            ];
        }

        if ($asked === null) {
            // Everything this can ask for has an answer. What is left — photos,
            // the day-by-day plan — is not something anyone can say aloud.
            return ['fields' => [], 'reply' => null, 'asked' => null, 'label' => null, 'choices' => null, 'done' => true,
                    'rejected' => [], 'unavailable' => false];
        }

        $schema = $this->schema($form, $known);

        // Two windows, not one.
        //
        // Every open field is named, because a key the model was not shown is a
        // key it discards — a member who says "three rooms, two people each"
        // has answered two things — and because a model shown only six fields,
        // having filled all six, decides the form is finished and asks nothing
        // more. That was the whole of the "no question came back" fault.
        //
        // Option lists are rationed to the first few, because those are what
        // actually cost: carrying all of them spent 4,700 tokens a turn against
        // an allowance of 8,000 a minute for the whole collective. A field
        // whose list is out of view is still asked about; it simply gets its
        // list once it comes near.
        $open = $this->stillOpen($schema, $known);

        $allowed = [];
        foreach (array_slice($open, 0, self::LISTS_AHEAD, true) as $key => $field) {
            $options = $this->allowedFor($field);
            if ($options !== null) {
                $allowed[$key] = $options;
            }
        }

        $prompt = app(PromptBuilderService::class)->build('provider_voice_form', [
            'still_missing' => json_encode(
                array_values(array_map(
                    fn ($key, $field) => ['key' => $key, 'ask' => $field['ask']],
                    array_keys($open),
                    $open,
                )),
                JSON_UNESCAPED_UNICODE,
            ),
            'allowed' => $allowed ? json_encode($allowed, JSON_UNESCAPED_UNICODE) : '(none — these are free text)',
            'known' => $known ? json_encode($known, JSON_UNESCAPED_UNICODE) : '(nothing yet)',
            'language' => $language === 'en' ? 'English' : 'Hindi',
            'said' => $said ?: '(they have not said anything yet)',
        ]);

        if (! $prompt) {
            // The prompt row is missing. Say so plainly rather than improvising
            // one here: a second copy in code is how the two drift apart.
            Log::error('Voice assistant prompt provider_voice_form is missing or inactive');

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'unavailable' => true];
        }

        $answer = app(GroqService::class)->chat([
            ['role' => 'system', 'content' => $prompt['system_prompt']],
            ['role' => 'user', 'content' => $prompt['user_prompt']],
        ], [
            'groq_model' => $prompt['model'] ?: null,
            'temperature' => $prompt['temperature'],
            'max_tokens' => $prompt['max_tokens'],
            'format' => 'json',
            // Reading one answer and naming the next field is not work that
            // wants deliberation, and deliberation here is charged twice: once
            // against the minute's token allowance, once against the member
            // sitting there waiting.
            'reasoning_effort' => 'low',
        ]);

        if (! $answer) {
            // The model was not reachable — out of the minute's allowance, or
            // simply down. Worth telling apart from "it heard nothing useful":
            // one is worth trying again in a moment, the other is not.
            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'unavailable' => true];
        }

        $data = json_decode($answer['content'], true);
        if (! is_array($data)) {
            Log::warning('Voice assistant returned unreadable JSON', [
                'content' => mb_substr((string) $answer['content'], 0, 300),
            ]);

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'unavailable' => false];
        }

        $checked = $this->keepValid($form, $known, (array) ($data['fields'] ?? []));
        $filled = $known + $checked['fields'];

        // Some answers change the shape of the form. Until a member says they
        // are offering a place to stay, a rate card has no room count to fill,
        // so a first breath of "three rooms, two thousand a night" was heard
        // and then thrown away for want of anywhere to put it. When the form
        // has just grown, read the same sentence once more against the fields
        // that now exist. Once only: the second pass cannot grow it again.
        if ($reread && count($this->schema($form, $filled)) > count($schema)) {
            $again = $this->turn($form, $filled, $said, $language, false);

            return [
                'fields' => $checked['fields'] + $again['fields'],
                'reply' => $again['reply'],
                'asked' => $again['asked'],
                'label' => $again['label'],
                'choices' => $again['choices'],
                'done' => $again['done'],
                'rejected' => array_values(array_unique(array_merge($checked['rejected'], $again['rejected']))),
                'unavailable' => $again['unavailable'],
            ];
        }

        // The next field is decided here, in the form's own order, and its own
        // question is read out. The model's part is done: it heard what was
        // said and said what it meant, and it is not asked what to ask next.
        $next = $this->nextField($form, $filled);

        return [
            'fields' => $checked['fields'],
            'reply' => $next === null ? null : $this->questionFor($form, $next, $language, $filled),
            'asked' => $next,
            'label' => $next === null ? null : $this->labelFor($form, $next, $filled),
            'choices' => $next === null ? null : $this->choicesFor($form, $next, $filled),
            'done' => $this->nextField($form, $filled) === null,
            'rejected' => $checked['rejected'],
            'unavailable' => false,
        ];
    }

    /** The fields still without an answer, so one reply can fill several. */
    private function stillOpen(array $schema, array $known): array
    {
        return array_filter(
            $schema,
            fn ($key) => ($known[$key] ?? null) === null || ($known[$key] ?? null) === '',
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Which language a member just asked to speak in.
     *
     * They are asked in English, before anything else, and answer out loud —
     * so the answer can be the word in either language, or simply spoken in
     * the language they want. Both are read: what they said first, and failing
     * that, what tongue they said it in.
     *
     * @param  string  $said  The transcript of their answer.
     * @param  ?string  $heardAs  The language the transcription reported.
     */
    public function languageFrom(string $said, ?string $heardAs = null): string
    {
        $text = mb_strtolower($said);

        foreach (['हिंदी', 'हिन्दी', 'hindi', 'hindī'] as $word) {
            if (str_contains($text, $word)) {
                return 'hi';
            }
        }
        foreach (['english', 'इंग्लिश', 'अंग्रेज', 'angrezi', 'angreji'] as $word) {
            if (str_contains($text, $word)) {
                return 'en';
            }
        }

        // They named no language, so take the one they used to answer in.
        return str_starts_with(mb_strtolower((string) $heardAs), 'en') ? 'en' : 'hi';
    }

    /**
     * The question that belongs to a field, in the language being spoken.
     *
     * Written down beside the field rather than composed by the model. Asking
     * a model to word the question meant it sometimes read the developer note
     * back to the member — "what their place is called" — and sometimes moved
     * on to a field further down the form because it seemed related. Neither
     * can happen to a sentence that was written once and is read out as it
     * stands, and it arrives instantly and costs nothing.
     */
    public function questionFor(string $form, string $field, string $language, array $known = []): ?string
    {
        $spec = $this->schema($form, $known)[$field] ?? null;

        return $spec['q'][$language] ?? $spec['q']['en'] ?? null;
    }

    /**
     * The choices to show beneath a question, or null when there are none.
     *
     * Only for fields fed by one of HCT's lists. The other constrained fields
     * hold internal codes — `less_than_day`, `easy` — and reading those out
     * would be worse than useless; their questions already name the choices in
     * words a person would use.
     */
    public function choicesFor(string $form, string $field, array $known = []): ?array
    {
        $spec = $this->schema($form, $known)[$field] ?? null;
        if (! $spec || ! isset($spec['list'])) {
            return null;
        }

        return $this->allowedFor($spec) ?: null;
    }

    /**
     * The label the app's own form shows above this field.
     *
     * Sent with the question so a member can see which box is being filled.
     * Word for word what the form says — a question about "your place" beside
     * a box headed something else is exactly the confusion this removes.
     */
    public function labelFor(string $form, string $field, array $known = []): ?string
    {
        return $this->schema($form, $known)[$field]['label'] ?? null;
    }

    /** The values a field will accept, or null when it takes free text. */
    public function allowedFor(array $field): ?array
    {
        if (isset($field['only'])) {
            return $field['only'];
        }
        if (isset($field['list'])) {
            return SystemList::ofType($field['list'])->pluck('name')->values()->all();
        }

        return null;
    }

    /**
     * Which field to ask about next, or null when there is nothing left.
     *
     * The code decides this, not the model. Letting a small model run the
     * conversation as well as read it meant it wandered — asking twice for the
     * same thing, or skipping ahead to a field that does not exist for the kind
     * of service it had just been told about.
     */
    public function nextField(string $form, array $known): ?string
    {
        foreach ($this->schema($form, $known) as $key => $field) {
            $value = $known[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Keep only what belongs: known field, right shape, and — where the field
     * is fed by one of HCT's lists — a value copied from it.
     *
     * The model is told all of this and mostly obeys, but "mostly" is not a
     * basis for writing to a rate card. Anything invented is dropped here.
     *
     * @return array{fields:array<string,mixed>,rejected:array<int,string>}
     */
    public function keepValid(string $form, array $known, array $offered): array
    {
        $schema = $this->schema($form, $known);
        $fields = [];
        $rejected = [];

        foreach ($offered as $key => $value) {
            $field = $schema[$key] ?? null;
            if (! $field || $value === null || $value === '') {
                $rejected[] = (string) $key;
                continue;
            }

            $allowed = $this->allowedFor($field);
            if ($allowed !== null) {
                // Case and spacing are the model's to get wrong; the value
                // itself is not. Match loosely, store exactly.
                $match = null;
                foreach ($allowed as $option) {
                    if (mb_strtolower(trim((string) $value)) === mb_strtolower($option)) {
                        $match = $option;
                        break;
                    }
                }
                if ($match === null) {
                    $rejected[] = (string) $key;
                    continue;
                }
                $fields[$key] = $match;
                continue;
            }

            $cast = $this->cast($field['type'] ?? 'string', $value);
            if ($cast === null) {
                $rejected[] = (string) $key;
                continue;
            }
            $fields[$key] = $cast;
        }

        return ['fields' => $fields, 'rejected' => $rejected];
    }

    /** Null when the value is not the shape the column needs. */
    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => is_numeric($value) ? (int) $value : null,
            'number' => is_numeric($value) ? (float) $value : null,
            'bool' => is_bool($value)
                ? $value
                : (in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'haan', 'ha'], true)
                    ? true
                    : (in_array(mb_strtolower(trim((string) $value)), ['0', 'false', 'no', 'nahi', 'nahin'], true)
                        ? false
                        : null)),
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }
}
