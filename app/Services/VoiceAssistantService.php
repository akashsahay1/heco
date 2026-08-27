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
     * The experience category that is a place to stay rather than something
     * that happens at a time. Spelled as HCT keeps it in the system list, and
     * as the app's own form spells it.
     */
    private const STAY = 'Experiential accommodation';

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
                'only' => ['accommodation', 'transport', 'guide', 'activity', 'rental', 'other'],
                'skippable' => false,
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
                'price' => ['label' => 'Rate per night (Rs)', 'ask' => 'what one room costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'meal_plan' => ['label' => 'Meal plan', 'ask' => 'which meals are included in that price', 'q' => ['hi' => 'इस दाम में कौन सा खाना शामिल है?', 'en' => 'Which meals are included in that price?'], 'type' => 'string', 'list' => 'meal_plan'],
                'default_occupancy' => ['label' => 'Default occupancy', 'ask' => 'whether the room is normally sold as a single, a double, and so on', 'q' => ['hi' => 'यह कमरा आम तौर पर किस हिसाब से दिया जाता है — सिंगल, डबल या कोई और?', 'en' => 'How is this room normally sold — as a single, a double, or something else?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'transport' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this vehicle on their rate card', 'q' => ['hi' => 'इस गाड़ी को रेट कार्ड पर क्या नाम दें?', 'en' => 'What should this vehicle be called on your rate card?'], 'type' => 'string'],
                'vehicle_type' => ['label' => 'Vehicle type', 'ask' => 'what kind of vehicle it is', 'q' => ['hi' => 'गाड़ी कौन सी है?', 'en' => 'What kind of vehicle is it?'], 'type' => 'string', 'list' => 'vehicle_type'],
                'vehicle_capacity' => ['label' => 'Seating capacity', 'ask' => 'how many passengers it seats', 'q' => ['hi' => 'इसमें कितने लोग बैठ सकते हैं?', 'en' => 'How many passengers does it seat?'], 'type' => 'int'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that is per day, per trip or per kilometre', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'driver_allowance' => ['label' => 'Driver allowance (Rs/day)', 'ask' => 'what the driver is paid on top, if anything', 'q' => ['hi' => 'ड्राइवर का अलग से कुछ खर्च है क्या?', 'en' => 'Is there anything paid to the driver on top?'], 'type' => 'number'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'guide' => [
                // The form's own control here is a picker over HCT's guide
                // types, not a free-text box. Treated as free text, whatever
                // the member said was stored and then shown as an empty
                // "Select" — a value they could neither see nor correct.
                'category' => ['label' => 'Guide type / language', 'ask' => 'what kind of guiding they do', 'q' => ['hi' => 'आप किस तरह की गाइडिंग करते हैं?', 'en' => 'What kind of guiding do you do?'], 'type' => 'string', 'list' => 'guide_preference'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what they guide — birds, forest, culture, and so on', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'price' => ['label' => 'Rate per day (Rs)', 'ask' => 'what they charge for a day', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'activity' => [
                // Also a picker in the form, over HCT's activity types.
                'category' => ['label' => 'Activity type', 'ask' => 'what kind of activity it is', 'q' => ['hi' => 'यह किस तरह की गतिविधि है?', 'en' => 'What kind of activity is it?'], 'type' => 'string', 'list' => 'activity_type'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what the activity involves', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that price is per person or per group', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'min_group' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
                'max_group' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            // The form offers this too, and without an arm of its own the
            // schema held nothing but service_type — which `known` already
            // answered — so the assistant said "that is everything I can ask
            // about" over a completely empty form.
            'other' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this service', 'q' => ['hi' => 'इस सेवा को क्या नाम दें?', 'en' => 'What should this service be called?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'what that price is for', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'occupancy_unit'],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'rental' => [
                'rental_item' => ['label' => 'Item on rent', 'ask' => 'what they rent out', 'q' => ['hi' => 'आप किराये पर क्या देते हैं?', 'en' => 'What do you rent out?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs to rent', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
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
        // The order the app's own form lays these out in, section by section:
        // Basic information, then Duration & schedule, Inclusions, Location,
        // Requirements, Costing, and Practical information last. A member
        // answering aloud is working down the same page they would otherwise
        // be tapping through, so the two must not drift apart.
        $basic = [
            'category' => ['label' => 'What kind of experience is this?', 'ask' => 'which category it belongs to', 'q' => ['hi' => 'यह किस श्रेणी का अनुभव है?', 'en' => 'Which category does this experience belong to?'], 'type' => 'string', 'list' => 'experience_category'],
            'name' => ['label' => 'Name', 'ask' => 'what the experience is called', 'q' => ['hi' => 'इस अनुभव का नाम क्या है?', 'en' => 'What is this experience called?'], 'type' => 'string'],
            'type' => ['label' => 'Type', 'ask' => 'what sort of experience it is', 'q' => ['hi' => 'यह किस तरह का अनुभव है?', 'en' => 'What sort of experience is it?'], 'type' => 'string', 'list' => 'experience_type'],
            'short_description' => ['label' => 'Short description', 'ask' => 'a sentence or two describing it to a traveller', 'q' => ['hi' => 'एक-दो लाइन में बताइए, यात्री को इसमें क्या मिलेगा?', 'en' => 'In a line or two, what does a traveller get from it?'], 'type' => 'string'],
            'long_description' => ['label' => 'Long description', 'ask' => 'the fuller story of the experience', 'q' => ['hi' => 'इस अनुभव की पूरी बात बताइए।', 'en' => 'Tell me the fuller story of this experience.'], 'type' => 'string'],
            'unique_description' => ['label' => 'What makes it unique', 'ask' => 'what makes this one different from anyone else offering something similar', 'q' => ['hi' => 'इसमें ऐसा क्या है जो और कहीं नहीं मिलेगा?', 'en' => 'What is there in this that a traveller would not find elsewhere?'], 'type' => 'string'],
            'cultural_context' => ['label' => 'Cultural context', 'ask' => 'anything about the place or its people a visitor ought to understand', 'q' => ['hi' => 'यहाँ के लोगों या रीति-रिवाज़ के बारे में यात्री को क्या समझना चाहिए?', 'en' => 'What should a visitor understand about this place and its people?'], 'type' => 'string'],
        ];

        // Duration & schedule. What the duration means in numbers depends on
        // which duration it is, so the follow-up appears only once they say.
        $duration = [
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
        ] + match ($known['duration_type'] ?? null) {
            'less_than_day' => [
                'duration_hours' => ['label' => 'Duration (hours)', 'ask' => 'how many hours it takes', 'q' => ['hi' => 'कितने घंटे लगते हैं?', 'en' => 'How many hours does it take?'], 'type' => 'number'],
            ],
            'multi_day' => [
                'duration_days' => ['label' => 'Days', 'ask' => 'how many days it runs', 'q' => ['hi' => 'कितने दिन चलता है?', 'en' => 'How many days does it run?'], 'type' => 'int'],
                'duration_nights' => ['label' => 'Nights', 'ask' => 'how many nights that includes', 'q' => ['hi' => 'इसमें कितनी रातें आती हैं?', 'en' => 'How many nights does that include?'], 'type' => 'int'],
            ],
            default => [],
        };

        // A stay is not a scheduled thing. The app's own form drops Duration,
        // Requirements and Costing the moment the category is a stay, and puts
        // rooms and beds in their place — so asking a homestay owner how hard
        // their experience is, and never asking how many rooms they have, both
        // stop here rather than being tidied up afterwards.
        if (($known['category'] ?? null) === self::STAY) {
            return $basic + [
                // The Inclusions section is not hidden for a stay, so a member
                // correcting "no, the stay itself is not part of it" must have
                // somewhere for that to land.
                'includes_accommodation' => ['label' => 'Accommodation', 'ask' => 'whether a place to stay is included', 'q' => ['hi' => 'रहने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is a place to stay included?'], 'type' => 'bool'],
                'includes_guide' => ['label' => 'Guide', 'ask' => 'whether a guide comes with it', 'q' => ['hi' => 'इसके साथ गाइड जाता है क्या?', 'en' => 'Does a guide go along with it?'], 'type' => 'bool'],
                'includes_transport' => ['label' => 'Transport', 'ask' => 'whether transport is included', 'q' => ['hi' => 'आने-जाने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is transport included?'], 'type' => 'bool'],
                'area' => ['label' => 'Area', 'ask' => 'the valley or area it happens in', 'q' => ['hi' => 'यह किस इलाके में होता है?', 'en' => 'Which area does it take place in?'], 'type' => 'string'],
                'total_rooms' => ['label' => 'Rooms', 'ask' => 'how many rooms the place has', 'q' => ['hi' => 'इस जगह में कितने कमरे हैं?', 'en' => 'How many rooms does the place have?'], 'type' => 'int'],
                'total_guests' => ['label' => 'Guests it sleeps', 'ask' => 'how many guests it sleeps in all', 'q' => ['hi' => 'कुल कितने मेहमान रुक सकते हैं?', 'en' => 'How many guests can stay in all?'], 'type' => 'int'],
                'traveller_bring_list' => ['label' => 'What travellers should bring', 'ask' => 'what a traveller should bring', 'q' => ['hi' => 'यात्री को अपने साथ क्या लाना चाहिए?', 'en' => 'What should a traveller bring with them?'], 'type' => 'string'],
            ];
        }

        return $basic + $duration + [
            // Inclusions
            'includes_accommodation' => ['label' => 'Accommodation', 'ask' => 'whether a place to stay is included', 'q' => ['hi' => 'रहने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is a place to stay included?'], 'type' => 'bool'],
            'includes_guide' => ['label' => 'Guide', 'ask' => 'whether a guide comes with it', 'q' => ['hi' => 'इसके साथ गाइड जाता है क्या?', 'en' => 'Does a guide go along with it?'], 'type' => 'bool'],
            'includes_transport' => ['label' => 'Transport', 'ask' => 'whether transport is included', 'q' => ['hi' => 'आने-जाने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is transport included?'], 'type' => 'bool'],
            // Location
            'area' => ['label' => 'Area', 'ask' => 'the valley or area it happens in', 'q' => ['hi' => 'यह किस इलाके में होता है?', 'en' => 'Which area does it take place in?'], 'type' => 'string'],
            // Requirements
            'difficulty_level' => [
                'label' => 'Difficulty level',
                'ask' => 'how hard it is physically',
                'q' => [
                    'hi' => 'यह शरीर से कितना मुश्किल है?',
                    'en' => 'How hard is it physically?',
                ],
                'type' => 'string',
                'only' => ['easy', 'moderate', 'challenging', 'extreme'],
            ],
            'group_size_min' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोग होने चाहिए?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
            'group_size_max' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोग आ सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
            // Costing
            'base_cost_per_person' => ['label' => 'Price pp (Rs)', 'ask' => 'what one person pays', 'q' => ['hi' => 'एक व्यक्ति का कितना लगता है?', 'en' => 'What does one person pay?'], 'type' => 'number'],
            // Practical information
            'traveller_bring_list' => ['label' => 'What travellers should bring', 'ask' => 'what a traveller should bring', 'q' => ['hi' => 'यात्री को अपने साथ क्या लाना चाहिए?', 'en' => 'What should a traveller bring with them?'], 'type' => 'string'],
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
     * @return array{fields:array<string,mixed>,reply:?string,asked:?string,label:?string,choices:?array,done:bool,rejected:array<int,string>,unavailable:bool}
     */
    public function turn(
        string $form,
        array $known,
        string $said,
        string $language = 'hi',
        array $skipped = [],
    ): array
    {
        $asked = $this->nextField($form, $known, $skipped);

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
                'note' => null,
                'unavailable' => false,
            ];
        }

        if ($asked === null) {
            // Everything this can ask for has an answer. What is left — photos,
            // the day-by-day plan — is not something anyone can say aloud.
            return ['fields' => [], 'reply' => null, 'asked' => null, 'label' => null, 'choices' => null, 'done' => true,
                    'rejected' => [], 'note' => null, 'unavailable' => false];
        }

        $schema = $this->schema($form, $known);

        // One field, and only its own option list. The model is asked to read
        // one answer, not to mine a sentence for everything it might contain —
        // so it needs nothing beyond the field in front of it. That also makes
        // a turn small, which matters: the free tier allows 8,000 tokens a
        // minute across the whole collective.
        $options = $this->allowedFor($schema[$asked]);

        $prompt = app(PromptBuilderService::class)->build('provider_voice_form', [
            // The question in the member's own words. Without it the model was
            // reading an answer against a field name and had no way to tell an
            // answer to THIS question from a sentence about something else —
            // so anything said became the value of whatever was open.
            'question' => $this->questionFor($form, $asked, $language, $known) ?? '',
            // The shape matters as much as the meaning. Told only what the
            // field is about, the model wrote "at least two people" into a box
            // that holds a whole number, and the answer was thrown away.
            'asked' => sprintf(
                '%s — %s. This field holds %s.',
                $asked,
                $schema[$asked]['ask'] ?? '',
                match ($schema[$asked]['type'] ?? 'string') {
                    'int' => 'a whole number, digits only',
                    'number' => 'a number, digits only',
                    'bool' => 'true or false',
                    default => 'text',
                },
            ),
            'allowed' => $options
                ? json_encode($options, JSON_UNESCAPED_UNICODE)
                : '(none — this field takes free text)',
            'said' => $said,
        ]);

        if (! $prompt) {
            // The prompt row is missing. Say so plainly rather than improvising
            // one here: a second copy in code is how the two drift apart.
            Log::error('Voice assistant prompt provider_voice_form is missing or inactive');

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true];
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
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true];
        }

        $data = json_decode($answer['content'], true);
        if (! is_array($data)) {
            Log::warning('Voice assistant returned unreadable JSON', [
                'content' => mb_substr((string) $answer['content'], 0, 300),
            ]);

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => false];
        }

        $checked = $this->keepValid($form, $known, (array) ($data['fields'] ?? []), $asked);

        // This turn's answer wins. Written the other way round, a member who
        // said "no, the name is Pradeep Homestay, not just Homestay" had their
        // correction quietly dropped in favour of what it was correcting.
        $filled = $checked['fields'] + $known;

        // Which field comes next, decided here in the form's own order — the
        // model is not asked what to ask, only what was said.
        $next = $this->nextField($form, $filled, $skipped);

        return [
            'fields' => $checked['fields'],
            // Nothing was taken and nothing was turned away — the answer did
            // not answer the question. Left unsaid, the same question simply
            // comes round again and the member repeats themselves at a screen
            // that looks deaf. It is the one thing they were never told.
            'note' => $checked['fields'] === [] && $checked['rejected'] === []
                ? $this->notHeard($form, $asked, $language, $known)
                : null,
            'reply' => $next === null ? null : $this->questionFor($form, $next, $language, $filled),
            'asked' => $next,
            'label' => $next === null ? null : $this->labelFor($form, $next, $filled),
            'choices' => $next === null ? null : $this->choicesFor($form, $next, $filled),
            'rejected' => $checked['rejected'],
            'done' => $next === null,
            'unavailable' => false,
        ];
    }

    /**
     * What is said when an answer did not answer the question.
     *
     * Named with the form's own heading — "Property name", "Total rooms" — so
     * a member can see which box is still waiting rather than guessing at
     * which of the last few questions went unheard.
     */
    private function notHeard(string $form, string $field, string $language, array $known): string
    {
        $label = $this->labelFor($form, $field, $known);

        return $language === 'hi'
            ? ($label ? "यह समझ नहीं आया — कृपया {$label} के बारे में बताइए।" : 'यह समझ नहीं आया।')
            : ($label ? "That did not answer it — please tell me about {$label}." : 'I did not catch that.');
    }

    /**
     * Which language a member just asked to speak in, or null if they did not.
     *
     * Two languages are on offer and no third is accepted. Reading the answer
     * loosely would be the wrong kindness here: it is the first thing asked,
     * and getting it wrong holds the whole conversation in a language the
     * member did not choose. So the word itself has to be there.
     *
     * It is matched forgivingly, though, because the answer is one word of
     * half a second and the transcription of it wobbles — a member saying
     * हिंदी has come back as "इन्दी" and as "हिन्नी", and both plainly mean
     * Hindi. What is not accepted is an answer with neither language in it:
     * "In the.", which is what a mis-heard word looks like, once settled the
     * conversation into English nobody asked for.
     *
     * @param  string  $said  The transcript of their answer.
     */
    public function languageFrom(string $said): ?string
    {
        // Punctuation and spacing carry nothing here and vary with every
        // transcription of the same word. Marks are kept along with letters:
        // in Devanagari the vowel signs ARE the word, and stripping them
        // leaves हिंदी as हद, which matches nothing.
        $text = preg_replace('/[^\p{L}\p{M}]+/u', '', mb_strtolower($said)) ?? '';

        foreach (['हिंद', 'हिन', 'इन्द', 'इंद', 'hind'] as $stem) {
            if (mb_strpos($text, $stem) !== false) {
                return 'hi';
            }
        }
        foreach (['english', 'ingli', 'इंग्ल', 'इंगल', 'अंग्रे', 'angre', 'angrej'] as $stem) {
            if (mb_strpos($text, $stem) !== false) {
                return 'en';
            }
        }

        // Neither language was named. They are asked again rather than being
        // given one of the two at a guess.
        return null;
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
     * `$skipped` is what the member has passed over. Without it the assistant
     * had no way to finish: a note nobody wants to leave, or an answer it
     * cannot make sense of, meant the same question for ever with no way
     * forward and no way to say so.
     *
     * The code decides this, not the model. Letting a small model run the
     * conversation as well as read it meant it wandered — asking twice for the
     * same thing, or skipping ahead to a field that does not exist for the kind
     * of service it had just been told about.
     */
    public function nextField(string $form, array $known, array $skipped = []): ?string
    {
        foreach ($this->schema($form, $known) as $key => $field) {
            // A field that decides the shape of the rest cannot be passed over:
            // skipping it left nothing to ask, and the sheet announced "that is
            // everything I can ask about" over a completely empty form.
            if (in_array($key, $skipped, true) && ($field['skippable'] ?? true)) {
                continue;
            }
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
    public function keepValid(string $form, array $known, array $offered, ?string $asked = null): array
    {
        $schema = $this->schema($form, $known);
        $lengths = $this->lengths($form);
        $fields = [];
        $rejected = [];

        foreach ($offered as $key => $value) {
            $field = $schema[$key] ?? null;
            if (! $field || $value === null || $value === '') {
                $rejected[] = (string) $key;
                continue;
            }

            // Only the field that was asked about. A member answers the
            // question in front of them; anything else the model reads into
            // the sentence is a deduction, and deductions were where the wrong
            // values came from — "homestay" in a name deciding the comfort
            // tier, three days walking becoming one night. A field filled
            // without being asked is never asked about again, so the guess
            // could not be corrected either.
            //
            // Nothing is lost by waiting: every field gets its own question.
            if ($asked !== null && $key !== $asked) {
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

            // Speech runs on where a form field stops. A member asked for "a
            // note for HECO" may talk for a paragraph, and the column holds
            // 255 characters — without this the save fails at the very end
            // with a message that says nothing about the assistant.
            $limit = $lengths[$key] ?? null;
            if ($limit !== null && is_string($cast) && mb_strlen($cast) > $limit) {
                $cast = rtrim(mb_substr($cast, 0, $limit - 1)) . '…';
            }

            $fields[$key] = $cast;
        }

        return ['fields' => $fields, 'rejected' => $rejected];
    }

    /**
     * What the columns behind each form will actually hold.
     *
     * Taken from the same rules the save endpoints enforce, so a spoken answer
     * is trimmed here rather than rejected there — where the member would see a
     * 422 about a field they never typed in.
     */
    private function lengths(string $form): array
    {
        return $form === 'rate'
            ? ['category' => 100, 'description' => 255, 'specialties' => 500,
               'unit' => 50, 'rental_item' => 150]
            : ['name' => 255, 'short_description' => 500, 'area' => 255,
               'long_description' => 65000];
    }

    /** Null when the value is not the shape the column needs. */
    private function cast(string $type, mixed $value): mixed
    {
        // A number spoken aloud arrives wearing its unit: "पाँच सौ रुपया रोज़"
        // is written down faithfully as "500 rupees per day", and refusing it
        // for not being bare digits threw away an answer the member had given
        // perfectly well. The figure is taken and the words around it dropped.
        if (in_array($type, ['int', 'number'], true) && is_string($value)) {
            $value = preg_match('/-?\d+(?:[.,]\d+)?/', str_replace(',', '', $value), $m)
                ? $m[0]
                : $value;
        }

        return match ($type) {
            'int' => is_numeric($value) ? (int) $value : null,
            // Whole where it is whole. A price handed back as 2000.0 was
            // written into a digits-only box as "2000.0"; the moment the member
            // typed one more digit the formatter swept the point away and two
            // thousand became two hundred thousand.
            'number' => is_numeric($value)
                ? ((float) $value == (int) $value ? (int) $value : (float) $value)
                : null,
            'bool' => is_bool($value)
                ? $value
                : (in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'haan', 'ha'], true)
                    ? true
                    : (in_array(mb_strtolower(trim((string) $value)), ['0', 'false', 'no', 'nahi', 'nahin'], true)
                        ? false
                        : null)),
            // A person asked what to bring says "shoes, a warm jacket, a torch
            // and water", and the model hands that back as a list. Refusing it
            // for not being a string left the field empty and the same question
            // asked over and over, with nothing on screen to say why.
            default => is_array($value)
                ? ($this->tidy(implode(', ', array_filter(array_map(
                    fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                    $value,
                )))) ?: null)
                : (is_scalar($value) ? ($this->tidy((string) $value) ?: null) : null),
        };
    }

    /**
     * Straighten out what the model wrote before it reaches a column.
     *
     * It favours typographic characters — a non-breaking hyphen in "3‑day", a
     * curly apostrophe — which display perfectly well and then fail to match
     * anything anyone types or searches for.
     */
    private function tidy(string $value): string
    {
        return trim(strtr($value, [
            "\u{2011}" => '-',   // non-breaking hyphen
            "\u{2013}" => '-',   // en dash
            "\u{2019}" => "'",   // right single quote
            "\u{00A0}" => ' ',   // non-breaking space
        ]));
    }
}
