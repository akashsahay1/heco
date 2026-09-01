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
     * turns into a question.
     *
     * Order is the order they are asked, and it is the order of the app's own
     * form — section by section, required boxes before optional ones. A member
     * has the form open in front of them while they talk: asked about a box
     * two sections further down, with the one under their thumb passed over,
     * they can only conclude the assistant has lost its place. It had not, but
     * the order was written by field rather than by page, so a guide was asked
     * for their specialties — an optional box — before their daily rate, which
     * sits directly beneath the question just answered.
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
                'default_occupancy' => ['label' => 'Default occupancy', 'ask' => 'whether the room is normally sold as a single, a double, and so on', 'q' => ['hi' => 'यह कमरा आम तौर पर किस हिसाब से दिया जाता है — सिंगल, डबल या कोई और?', 'en' => 'How is this room normally sold — as a single, a double, or something else?'], 'type' => 'string', 'list' => 'room_occupancy'],
                // Nobody says their own coordinates aloud, and a misheard
                // digit puts the place in the wrong valley — so this is said
                // rather than asked.
                'coordinates' => ['label' => 'Latitude & longitude', 'manual' => ['hi' => 'नक्शे पर जगह का निशान — अक्षांश और देशांतर — आपको फ़ॉर्म में खुद भरना होगा। बोलकर नहीं हो सकता, और एक अंक भी ग़लत सुना गया तो जगह दूसरी घाटी में चली जाएगी।', 'en' => 'The map pin — latitude and longitude — you will need to fill in yourself. It cannot be spoken, and one digit heard wrong puts your place in the wrong valley.']],
                'guest_capacity' =>['label' => 'Guests it sleeps', 'ask' => 'how many guests the place sleeps in all', 'q' => ['hi' => 'कुल कितने मेहमान रुक सकते हैं?', 'en' => 'How many guests can stay in all?'], 'type' => 'int'],
                'seasonality_notes' => ['label' => 'Seasonality', 'ask' => 'which months they take guests, and which they do not', 'q' => ['hi' => 'साल के किन महीनों में मेहमान आ सकते हैं?', 'en' => 'Which months of the year can guests come?'], 'type' => 'string'],
                'photos' => ['label' => 'Photos', 'manual' => ['hi' => 'तस्वीरें आपको खुद जोड़नी होंगी — फ़ॉर्म में Photos वाले हिस्से से। यात्री सबसे पहले वही देखता है, इसलिए दो-तीन अच्छी तस्वीरें ज़रूर लगाइए।', 'en' => 'Photos you will need to add yourself, from the Photos part of the form. They are the first thing a traveller looks at, so put two or three good ones in.']],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'transport' => [
                'category' => ['label' => 'Vehicle name', 'ask' => 'what to call this vehicle on their rate card', 'q' => ['hi' => 'इस गाड़ी को रेट कार्ड पर क्या नाम दें?', 'en' => 'What should this vehicle be called on your rate card?'], 'type' => 'string'],
                'vehicle_type' => ['label' => 'Vehicle type', 'ask' => 'what kind of vehicle it is', 'q' => ['hi' => 'गाड़ी कौन सी है?', 'en' => 'What kind of vehicle is it?'], 'type' => 'string', 'list' => 'vehicle_type'],
                'vehicle_make_model' => ['label' => 'Make & model', 'ask' => 'the make and model of the vehicle', 'q' => ['hi' => 'गाड़ी का मेक और मॉडल क्या है?', 'en' => 'What is the make and model of the vehicle?'], 'type' => 'string'],
                // Letters and digits with no sense to check them against, so a
                // member has no way of knowing it went down wrong — and a
                // wrong one names somebody else's vehicle. Asked slowly, and
                // read straight back so the mistake is caught while they are
                // still listening rather than at the save button.
                'vehicle_registration_no' => [
                    'label' => 'Registration no.',
                    'ask' => 'the vehicle\'s registration number, exactly as it is written on the plate, letters and digits with no spaces changed',
                    'q' => [
                        'hi' => 'गाड़ी का नंबर बताइए — धीरे-धीरे, एक-एक अक्षर और अंक।',
                        'en' => 'What is the vehicle\'s registration number? Say it slowly, letter by letter.',
                    ],
                    'type' => 'string',
                    'echo' => [
                        'hi' => 'मैंने लिखा है: %s — अगर ग़लत है तो फ़ॉर्म में ठीक कर लीजिए।',
                        'en' => 'I have written: %s — if that is wrong, correct it in the form.',
                    ],
                ],
                'vehicle_year' =>['label' => 'Year', 'ask' => 'which year the vehicle is from', 'q' => ['hi' => 'गाड़ी किस साल की है?', 'en' => 'What year is the vehicle from?'], 'type' => 'int'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that is per day, per trip or per kilometre', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'transport_unit'],
                // Optional details, and the form keeps them in their own
                // section below the required ones.
                'vehicle_capacity' => ['label' => 'Seating capacity', 'ask' => 'how many passengers it seats', 'q' => ['hi' => 'इसमें कितने लोग बैठ सकते हैं?', 'en' => 'How many passengers does it seat?'], 'type' => 'int'],
                'driver_allowance' => ['label' => 'Driver allowance (Rs/day)', 'ask' => 'what the driver is paid on top, if anything', 'q' => ['hi' => 'ड्राइवर का अलग से कुछ खर्च है क्या?', 'en' => 'Is there anything paid to the driver on top?'], 'type' => 'number'],
                // The three that decide who pays. Each is asked as a choice
                // between the two states rather than as a polarity — a member
                // answering "no, it is included" is describing the other
                // state, not negating the question — and each is read back in
                // words, because a switch looks the same however it got there.
                'driver_included' => [
                    'label' => 'Driver included',
                    'ask' => 'whether a driver comes with that rate, or the traveller arranges one',
                    'q' => ['hi' => 'क्या इस दाम में ड्राइवर शामिल है?', 'en' => 'Does that rate include a driver?'],
                    'type' => 'bool',
                    'echo' => [
                        'hi' => ['true' => 'ठीक है — ड्राइवर दाम में शामिल है।', 'false' => 'ठीक है — ड्राइवर दाम में शामिल नहीं है।'],
                        'en' => ['true' => 'Noted — the driver comes with the rate.', 'false' => 'Noted — the driver is not included in the rate.'],
                    ],
                ],
                'fuel_tolls_extra' => [
                    'label' => 'Fuel & tolls billed separately',
                    'ask' => 'whether fuel and tolls are charged on top of the rate. True when they are extra and the traveller pays them, false when the rate already covers them',
                    // Asked as a plain yes or no, and left that way. Worded as
                    // a choice between the two states — "included, or charged
                    // on top?" — it read better and cost more: a bare "no",
                    // which is what most people answer, stopped meaning
                    // anything and was recorded as the opposite.
                    'q' => ['hi' => 'क्या तेल और टोल अलग से लगते हैं?', 'en' => 'Are fuel and tolls charged separately?'],
                    'type' => 'bool',
                    'echo' => [
                        'hi' => ['true' => 'ठीक है — तेल और टोल अलग से लगेंगे।', 'false' => 'ठीक है — तेल और टोल दाम में शामिल हैं।'],
                        'en' => ['true' => 'Noted — fuel and tolls are charged on top.', 'false' => 'Noted — fuel and tolls are included in the rate.'],
                    ],
                ],
                'vehicle_photos' => ['label' => 'Vehicle photos', 'manual' => ['hi' => 'गाड़ी की तस्वीरें आपको खुद जोड़नी होंगी — बोलकर नहीं हो सकतीं।', 'en' => 'Photos of the vehicle you will need to add yourself — they cannot be spoken.']],
                'price_per_km_plains' =>['label' => 'Cost per km — plains (Rs)', 'ask' => 'what a kilometre costs on flat roads', 'q' => ['hi' => 'मैदान में एक किलोमीटर का कितना लगता है?', 'en' => 'What does a kilometre cost on the plains?'], 'type' => 'number'],
                'price_per_km_hills' => ['label' => 'Cost per km — hills (Rs)', 'ask' => 'what a kilometre costs in the hills', 'q' => ['hi' => 'पहाड़ में एक किलोमीटर का कितना लगता है?', 'en' => 'What does a kilometre cost in the hills?'], 'type' => 'number'],
                'vehicle_count' => ['label' => 'Number of vehicles', 'ask' => 'how many such vehicles they run', 'q' => ['hi' => 'ऐसी कितनी गाड़ियाँ हैं आपके पास?', 'en' => 'How many such vehicles do you have?'], 'type' => 'int'],
                'ac_available' => [
                    'label' => 'Air conditioning available',
                    'ask' => 'whether the vehicle has air conditioning',
                    'q' => ['hi' => 'क्या गाड़ी में एसी है?', 'en' => 'Does the vehicle have air conditioning?'],
                    'type' => 'bool',
                    'echo' => [
                        'hi' => ['true' => 'ठीक है — गाड़ी में एसी है।', 'false' => 'ठीक है — गाड़ी में एसी नहीं है।'],
                        'en' => ['true' => 'Noted — the vehicle has air conditioning.', 'false' => 'Noted — the vehicle has no air conditioning.'],
                    ],
                ],
                'ac_extra_cost' => ['label' => 'Extra cost for AC (Rs)', 'ask' => 'what air conditioning costs on top, if anything', 'q' => ['hi' => 'एसी का अलग से कितना लगता है?', 'en' => 'What does air conditioning cost on top?'], 'type' => 'number'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'guide' => [
                // The form's own control here is a picker over HCT's guide
                // types, not a free-text box. Treated as free text, whatever
                // the member said was stored and then shown as an empty
                // "Select" — a value they could neither see nor correct.
                'category' => ['label' => 'Guide type / language', 'ask' => 'what kind of guiding they do', 'q' => ['hi' => 'आप किस तरह की गाइडिंग करते हैं?', 'en' => 'What kind of guiding do you do?'], 'type' => 'string', 'list' => 'guide_preference'],
                'price' => ['label' => 'Rate per day (Rs)', 'ask' => 'what they charge for a day', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                // Optional details, in the order the form's own section has them.
                'specialties' => ['label' => 'Specialties', 'ask' => 'what they guide — birds, forest, culture, and so on', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'wage_multi_day' => ['label' => 'Rate per day — multi-day with night stay (Rs)', 'ask' => 'what they charge a day on a trip where they stay the night', 'q' => ['hi' => 'जिस काम में रात रुकना पड़े, उसका एक दिन का कितना लेते हैं?', 'en' => 'On a trip where you stay the night, what do you charge for a day?'], 'type' => 'number'],
                'languages' => ['label' => 'Other languages', 'ask' => 'which languages they can guide in', 'q' => ['hi' => 'आप किन-किन भाषाओं में गाइड कर सकते हैं?', 'en' => 'Which languages can you guide in?'], 'type' => 'multi', 'list' => 'language'],
                'speaks_english' => ['label' => 'Speaks English', 'ask' => 'whether they speak English', 'q' => ['hi' => 'क्या आप अंग्रेज़ी बोल लेते हैं?', 'en' => 'Do you speak English?'], 'type' => 'bool'],
                'is_certified' => ['label' => 'Certified guide', 'ask' => 'whether they hold a guiding certificate', 'q' => ['hi' => 'क्या आपके पास गाइड का कोई सर्टिफिकेट है?', 'en' => 'Do you hold a guiding certificate?'], 'type' => 'bool'],
                'has_first_aid' => ['label' => 'First-aid trained', 'ask' => 'whether they are trained in first aid', 'q' => ['hi' => 'क्या आपने फर्स्ट-एड की ट्रेनिंग ली है?', 'en' => 'Have you had first-aid training?'], 'type' => 'bool'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'activity' => [
                // Also a picker in the form, over HCT's activity types.
                'category' => ['label' => 'Activity type', 'ask' => 'what kind of activity it is', 'q' => ['hi' => 'यह किस तरह की गतिविधि है?', 'en' => 'What kind of activity is it?'], 'type' => 'string', 'list' => 'activity_type'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that price is per person or per group', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'activity_unit'],
                // Optional details, in the order the form's own section has them.
                'min_group' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
                'max_group' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what the activity involves', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            // The form offers this too, and without an arm of its own the
            // schema held nothing but service_type — which `known` already
            // answered — so the assistant said "that is everything I can ask
            // about" over a completely empty form.
            'other' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this service', 'q' => ['hi' => 'इस सेवा को क्या नाम दें?', 'en' => 'What should this service be called?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'what that price is for — per person, per day, per piece, whatever they charge by', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string'],
            ],
            'rental' => [
                'rental_item' => ['label' => 'Item on rent', 'ask' => 'what they rent out', 'q' => ['hi' => 'आप किराये पर क्या देते हैं?', 'en' => 'What do you rent out?'], 'type' => 'string'],
                'price' => ['label' => 'Charges per day (Rs)', 'ask' => 'what it costs to rent for a day', 'q' => ['hi' => 'एक दिन का किराया कितना है?', 'en' => 'What does it cost to rent for a day?'], 'type' => 'number'],
                                'security_deposit' => ['label' => 'Security deposit (Rs)', 'ask' => 'what deposit they hold, if any', 'q' => ['hi' => 'कितनी रकम जमानत के तौर पर रखते हैं?', 'en' => 'What deposit do you hold?'], 'type' => 'number'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
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
        $stay = ($known['category'] ?? null) === self::STAY;

        // Basic information.
        $basic = [
            'category' => ['label' => 'What kind of experience is this?', 'ask' => 'which category it belongs to', 'q' => ['hi' => 'यह किस श्रेणी का अनुभव है?', 'en' => 'Which category does this experience belong to?'], 'type' => 'string', 'list' => 'experience_category',
                // Decides the whole shape of the form below it, the way
                // service_type does for a rate card: a stay is asked about
                // rooms and beds, everything else about duration, difficulty
                // and cost. Passed over, a homestay would be walked through
                // an outing's questions and never asked how many rooms it has.
                'skippable' => false],
            'name' => ['label' => 'Name', 'ask' => 'what the experience is called', 'q' => ['hi' => 'इस अनुभव का नाम क्या है?', 'en' => 'What is this experience called?'], 'type' => 'string'],
            'type' => ['label' => 'Type', 'ask' => 'what sort of experience it is', 'q' => ['hi' => 'यह किस तरह का अनुभव है?', 'en' => 'What sort of experience is it?'], 'type' => 'string', 'list' => 'experience_type'],
            // The valleys HECO works in. Offered by name and stored by id —
            // see allowedFor() and keepValid().
            'region_id' => ['label' => 'Region', 'ask' => 'which region it belongs to', 'q' => ['hi' => 'यह किस क्षेत्र में आता है?', 'en' => 'Which region does it belong to?'], 'type' => 'string', 'source' => 'regions'],
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
        } + [
            'start_time' => ['label' => 'Start time', 'ask' => 'what time of day it starts', 'q' => ['hi' => 'दिन में किस समय शुरू होता है?', 'en' => 'What time of day does it start?'], 'type' => 'string'],
            'end_time' => ['label' => 'End time', 'ask' => 'what time it finishes', 'q' => ['hi' => 'किस समय ख़त्म होता है?', 'en' => 'What time does it finish?'], 'type' => 'string'],
        ];

        // Inclusions. The comfort tier only matters once a bed is part of it.
        $inclusions = [
            'includes_accommodation' => ['label' => 'Accommodation', 'ask' => 'whether a place to stay is included', 'q' => ['hi' => 'रहने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is a place to stay included?'], 'type' => 'bool'],
        ] + (($known['includes_accommodation'] ?? false) ? [
            'accommodation_category' => ['label' => 'Accommodation category', 'ask' => 'what sort of place travellers stay in', 'q' => ['hi' => 'यात्री किस तरह की जगह पर रुकते हैं?', 'en' => 'What sort of place do travellers stay in?'], 'type' => 'string', 'list' => 'accommodation_category'],
        ] : []) + [
            'includes_meals_breakfast' => ['label' => 'Breakfast', 'ask' => 'whether breakfast is included', 'q' => ['hi' => 'नाश्ता इसमें शामिल है?', 'en' => 'Is breakfast included?'], 'type' => 'bool'],
            'includes_meals_lunch' => ['label' => 'Lunch', 'ask' => 'whether lunch is included', 'q' => ['hi' => 'दोपहर का खाना शामिल है?', 'en' => 'Is lunch included?'], 'type' => 'bool'],
            'includes_meals_dinner' => ['label' => 'Dinner', 'ask' => 'whether dinner is included', 'q' => ['hi' => 'रात का खाना शामिल है?', 'en' => 'Is dinner included?'], 'type' => 'bool'],
            'includes_guide' => ['label' => 'Guide', 'ask' => 'whether a guide comes with it', 'q' => ['hi' => 'इसके साथ गाइड जाता है क्या?', 'en' => 'Does a guide go along with it?'], 'type' => 'bool'],
            'includes_transport' => ['label' => 'Transport', 'ask' => 'whether transport is included', 'q' => ['hi' => 'आने-जाने का इंतज़ाम इसमें शामिल है?', 'en' => 'Is transport included?'], 'type' => 'bool'],
        ];

        $location = [
            'area' => ['label' => 'Area', 'ask' => 'the valley or area it happens in', 'q' => ['hi' => 'यह किस इलाके में होता है?', 'en' => 'Which area does it take place in?'], 'type' => 'string'],
            'trekking_required' => ['label' => 'Trekking required', 'ask' => 'whether travellers have to walk to reach it', 'q' => ['hi' => 'क्या यहाँ पहुँचने के लिए पैदल चलना पड़ता है?', 'en' => 'Do travellers have to walk to get there?'], 'type' => 'bool'],
            'road_seasonal_closure' => ['label' => 'Road closes seasonally', 'ask' => 'whether the road shuts at some times of year', 'q' => ['hi' => 'क्या साल के किसी समय रास्ता बंद हो जाता है?', 'en' => 'Does the road close at some times of year?'], 'type' => 'bool'],
        ];

        // Requirements. A stay has none of this — it is not a thing that takes
        // a body somewhere.
        $requirements = [
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
            'fitness_requirements' => ['label' => 'Fitness requirements', 'ask' => 'how fit a traveller needs to be', 'q' => ['hi' => 'यात्री का शरीर कितना चलने-फिरने लायक होना चाहिए?', 'en' => 'How fit does a traveller need to be for this?'], 'type' => 'string'],
            'weather_dependency' => ['label' => 'Weather dependency', 'ask' => 'how the weather affects it', 'q' => ['hi' => 'मौसम का इस पर क्या असर पड़ता है?', 'en' => 'How does the weather affect it?'], 'type' => 'string'],
            'cultural_sensitivities' => ['label' => 'Cultural sensitivities', 'ask' => 'anything a visitor should be careful about', 'q' => ['hi' => 'यात्री को किन बातों का ध्यान रखना चाहिए?', 'en' => 'Is there anything a visitor should be careful about?'], 'type' => 'string'],
            'environmental_constraints' => ['label' => 'Environmental constraints', 'ask' => 'anything about the place that limits how many people can come, or when', 'q' => ['hi' => 'जगह की वजह से कोई पाबंदी है — कितने लोग आ सकते हैं, या कब?', 'en' => 'Does the place itself limit how many can come, or when?'], 'type' => 'string'],
            'group_size_min' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोग होने चाहिए?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
            'group_size_max' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोग आ सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
        ];

        // Seasonality. Months are numbers here because that is how the form
        // stores them; a member says "March to June" and means 3, 4, 5, 6.
        $seasonality = [
            'best_seasons' => ['label' => 'Best seasons', 'ask' => 'which seasons are the best time to come', 'q' => ['hi' => 'आने का सबसे अच्छा मौसम कौन सा है?', 'en' => 'Which seasons are the best time to come?'], 'type' => 'multi', 'list' => 'best_season'],
            'available_months' => ['label' => 'Available months', 'ask' => 'which months of the year it runs', 'q' => ['hi' => 'साल के किन महीनों में यह होता है?', 'en' => 'Which months of the year does it run?'], 'type' => 'months'],
            'restricted_months' => ['label' => 'Restricted months', 'ask' => 'which months it runs only with difficulty', 'q' => ['hi' => 'किन महीनों में यह मुश्किल से हो पाता है?', 'en' => 'In which months does it run only with difficulty?'], 'type' => 'months'],
            'unavailable_months' => ['label' => 'Unavailable months', 'ask' => 'which months it does not run at all', 'q' => ['hi' => 'किन महीनों में यह बिल्कुल नहीं होता?', 'en' => 'In which months does it not run at all?'], 'type' => 'months'],
            'seasonality_notes' => ['label' => 'Seasonality notes', 'ask' => 'anything else about the seasons here', 'q' => ['hi' => 'मौसम के बारे में और कुछ बताना चाहेंगे?', 'en' => 'Anything else about the seasons here?'], 'type' => 'string'],
        ];

        // Every box below this point is a table or a file. Each is announced
        // as it is reached rather than passed over: a member who is never told
        // about the itinerary finishes the conversation believing the listing
        // is finished too, and only the save button disagrees.
        $byHand = [
            'experience_days' => [
                'label' => 'Day-wise itinerary',
                'more' => [
                    'q' => ['hi' => 'क्या आप दिन-ब-दिन का कार्यक्रम बताना चाहेंगे?', 'en' => 'Would you like to tell me the day-by-day plan?'],
                    'q_more' => ['hi' => 'अगला दिन बताइए?', 'en' => 'Shall we do the next day?'],
                ],
                'row' => [
                    'title' => [
                        'ask' => 'a short name for that day',
                        'q' => ['hi' => 'उस दिन को छोटा सा नाम दीजिए।', 'en' => 'Give that day a short name.'],
                        'type' => 'string',
                    ],
                    'short_description' => [
                        'ask' => 'what happens on that day',
                        'q' => ['hi' => 'उस दिन क्या-क्या होता है?', 'en' => 'What happens on that day?'],
                        'type' => 'string',
                    ],
                    'inclusions' => [
                        'ask' => 'what that day includes — meals, a place to stay, a guide, transport',
                        'q' => ['hi' => 'उस दिन में क्या-क्या शामिल है — खाना, रहना, गाइड, आना-जाना?', 'en' => 'What does that day include — meals, a bed, a guide, transport?'],
                        'type' => 'multi',
                        'list' => 'day_inclusion',
                    ],
                ],
            ],
            'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं — जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this — an extra mattress, a pickup from the station?',
                        ],
                        'q_more' => [
                            'hi' => 'और कोई चीज़?',
                            'en' => 'Anything else?',
                        ],
                    ],
                    'row' => [
                        'name' => [
                            'ask' => 'what that extra is called',
                            'q' => ['hi' => 'उस चीज़ का नाम क्या है?', 'en' => 'What is that one called?'],
                            'type' => 'string',
                        ],
                        'price' => [
                            'ask' => 'what that extra costs',
                            'q' => ['hi' => 'उसका दाम कितना है?', 'en' => 'What does it cost?'],
                            'type' => 'number',
                        ],
                    ],
                ],
            'gallery' => ['label' => 'Photos', 'manual' => [
                'hi' => 'तस्वीरें आपको खुद जोड़नी होंगी — एक कार्ड वाली तस्वीर और बाकी गैलरी में। बोलकर तस्वीर नहीं बनती, और यात्री सबसे पहले वही देखता है।',
                'en' => 'Photos you will need to add yourself — one for the card and the rest in the gallery. A microphone does not take pictures, and they are the first thing a traveller looks at.',
            ]],
        ];

        // Practical information, then operational notes: the last two sections
        // of the form, and both plain prose.
        $practical = [
            'osps_involved' => ['label' => 'Other service providers involved', 'ask' => 'whether anyone else from the collective is part of this', 'q' => ['hi' => 'क्या इसमें कोई और साथी भी शामिल है?', 'en' => 'Is anyone else from the collective involved in this?'], 'type' => 'bool'],
        ] + (($known['osps_involved'] ?? false) ? [
            'osp_services' => ['label' => 'OSP services', 'ask' => 'what those others provide', 'q' => ['hi' => 'वे क्या-क्या सेवा देते हैं?', 'en' => 'What do they provide?'], 'type' => 'multi', 'list' => 'service_type'],
        ] : []) + [
            'traveller_bring_list' => ['label' => 'What travellers should bring', 'ask' => 'what a traveller should bring', 'q' => ['hi' => 'यात्री को अपने साथ क्या लाना चाहिए?', 'en' => 'What should a traveller bring with them?'], 'type' => 'string'],
            'clothing_recommendations' => ['label' => 'Clothing recommendations', 'ask' => 'what a traveller should wear', 'q' => ['hi' => 'यात्री को कैसे कपड़े पहनने चाहिए?', 'en' => 'What should a traveller wear?'], 'type' => 'string'],
            'health_notes' => ['label' => 'Health notes', 'ask' => 'anything about health a traveller should know', 'q' => ['hi' => 'सेहत के बारे में यात्री को कुछ बताना ज़रूरी है?', 'en' => 'Is there anything about health a traveller should know?'], 'type' => 'string'],
            'connectivity_notes' => ['label' => 'Connectivity notes', 'ask' => 'whether there is phone signal or internet there', 'q' => ['hi' => 'वहाँ फ़ोन का नेटवर्क या इंटरनेट मिलता है?', 'en' => 'Is there phone signal or internet there?'], 'type' => 'string'],
            'cultural_etiquette' => ['label' => 'Cultural etiquette', 'ask' => 'how a visitor should behave with local people', 'q' => ['hi' => 'यात्री को यहाँ के लोगों के साथ कैसे पेश आना चाहिए?', 'en' => 'How should a visitor behave with local people?'], 'type' => 'string'],
        ];

        $operational = [
            'operational_risks' => ['label' => 'Operational risks', 'ask' => 'what could go wrong on the day', 'q' => ['hi' => 'उस दिन क्या-क्या गड़बड़ हो सकती है?', 'en' => 'What could go wrong on the day?'], 'type' => 'string'],
            'past_issues' => ['label' => 'Past issues', 'ask' => 'anything that has gone wrong before', 'q' => ['hi' => 'पहले कभी कुछ गड़बड़ हुई है? क्या?', 'en' => 'Has anything gone wrong before? What?'], 'type' => 'string'],
            'backup_options' => ['label' => 'Backup options', 'ask' => 'what they do instead when it cannot go ahead', 'q' => ['hi' => 'अगर यह न हो पाए तो उसकी जगह क्या करते हैं?', 'en' => 'If this cannot go ahead, what do you do instead?'], 'type' => 'string'],
            'emergency_notes' => ['label' => 'Emergency notes', 'ask' => 'what happens in an emergency, and who is called', 'q' => ['hi' => 'आपात स्थिति में क्या करते हैं, और किसे बुलाते हैं?', 'en' => 'In an emergency, what do you do and who do you call?'], 'type' => 'string'],
        ];

        // A stay is not a scheduled thing. The app's own form drops Duration,
        // Requirements and Costing the moment the category is a stay, and puts
        // rooms and beds in their place — so asking a homestay owner how hard
        // their experience is, and never asking how many rooms they have, both
        // stop here rather than being tidied up afterwards.
        if ($stay) {
            return $basic + $inclusions + $location + $seasonality + [
                'total_rooms' => ['label' => 'Rooms', 'ask' => 'how many rooms the place has', 'q' => ['hi' => 'इस जगह में कितने कमरे हैं?', 'en' => 'How many rooms does the place have?'], 'type' => 'int'],
                'total_guests' => ['label' => 'Guests it sleeps', 'ask' => 'how many guests it sleeps in all', 'q' => ['hi' => 'कुल कितने मेहमान रुक सकते हैं?', 'en' => 'How many guests can stay in all?'], 'type' => 'int'],
                'room_rates' => [
                    'label' => 'Rooms and prices',
                    'more' => [
                        'q' => ['hi' => 'क्या किसी कमरे का दाम बताना चाहेंगे?', 'en' => 'Would you like to give me a room and its price?'],
                        'q_more' => ['hi' => 'और कोई कमरा?', 'en' => 'Another room?'],
                    ],
                    'row' => [
                        'occupancy' => [
                            'ask' => 'which kind of room this price is for',
                            'q' => ['hi' => 'यह दाम किस तरह के कमरे का है?', 'en' => 'Which kind of room is this price for?'],
                            'type' => 'string',
                            'list' => 'room_category',
                        ],
                        'meal_plan' => [
                            'ask' => 'which meals that price includes',
                            'q' => ['hi' => 'इस दाम में कौन सा खाना शामिल है?', 'en' => 'Which meals does that price include?'],
                            'type' => 'string',
                            'list' => 'meal_plan',
                        ],
                        'price' => [
                            'ask' => 'what that room costs a night',
                            'q' => ['hi' => 'उस कमरे का एक रात का कितना?', 'en' => 'What does that room cost a night?'],
                            'type' => 'number',
                        ],
                    ],
                ],
            ] + $byHand + $practical + $operational;
        }

        return $basic + $duration + $inclusions + $location + $requirements + $seasonality + [
            'base_cost_per_person' => ['label' => 'Price pp (Rs)', 'ask' => 'what one person pays', 'q' => ['hi' => 'एक व्यक्ति का कितना लगता है?', 'en' => 'What does one person pay?'], 'type' => 'number'],
            'price_slabs' => [
                'label' => 'Per-person price tiers',
                'more' => [
                    'q' => ['hi' => 'क्या बड़े समूह का दाम अलग होता है?', 'en' => 'Does a bigger group pay a different rate?'],
                    'q_more' => ['hi' => 'और कोई स्लैब?', 'en' => 'Another tier?'],
                ],
                'row' => [
                    'min_persons' => [
                        'ask' => 'the smallest number of people that tier starts at',
                        'q' => ['hi' => 'यह दाम कितने लोगों से शुरू होता है?', 'en' => 'From how many people does that rate start?'],
                        'type' => 'int',
                    ],
                    'price_per_person' => [
                        'ask' => 'what one person pays at that size',
                        'q' => ['hi' => 'उतने लोगों पर एक व्यक्ति का कितना?', 'en' => 'At that size, what does one person pay?'],
                        'type' => 'number',
                    ],
                ],
            ],
        ] + $byHand + $practical + $operational;
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
        // Where the form has got to, and a word about any box reached on the
        // way that nobody can fill by talking.
        $here = $this->walk($form, $known, $skipped, $language);
        $asked = $here['next'];

        // Nothing said means nothing to read: hand back the question for
        // wherever the form has got to, without troubling the model at all.
        if ($asked !== null && trim($said) === '') {
            return [
                'guidance' => $here['guidance'],
                'passed' => $here['passed'],
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
                    'rejected' => [], 'note' => null, 'unavailable' => false,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        $schema = $this->schema($form, $known);

        // One field, and only its own option list. The model is asked to read
        // one answer, not to mine a sentence for everything it might contain —
        // so it needs nothing beyond the field in front of it. That also makes
        // a turn small, which matters: the free tier allows 8,000 tokens a
        // minute across the whole collective.
        // A column of a table answers to the same rules as a box on the form,
        // so everything below reads the field through the same resolver.
        $spec = $this->specFor($form, $asked, $known);

        $options = $this->allowedFor($spec);

        // What those options mean, where HCT has said. Bounded by the same
        // rule: one field's list, not the whole form's.
        $meanings = $this->meaningsFor($spec);

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
                $spec['ask'] ?? '',
                match ($spec['type'] ?? 'string') {
                    'int' => 'a whole number, digits only',
                    'number' => 'a number, digits only',
                    'bool' => 'true or false',
                    // Several of the allowed values at once, as a JSON array.
                    // Told it held text, the model wrote "Hindi and English"
                    // into a box that takes a list, and the whole answer was
                    // turned away for matching nothing.
                    'multi' => 'a JSON array of one or more of the allowed values',
                    // "March to June" is four months, not a sentence.
                    'months' => 'a JSON array of month numbers, 1 for January through 12 for December, with every month they name spelled out — a range like March to June is [3, 4, 5, 6]',
                    default => 'text',
                },
            ),
            'allowed' => $options
                ? json_encode($options, JSON_UNESCAPED_UNICODE)
                : '(none — this field takes free text)',
            // The notes carry their own heading rather than the template
            // carrying it, so a list with nothing written beside it leaves no
            // empty heading behind for the model to wonder about.
            'meanings' => $meanings === null ? '' : "\n\nWhat each of those covers:\n" . $meanings,
            'said' => $said,
        ]);

        if (! $prompt) {
            // The prompt row is missing. Say so plainly rather than improvising
            // one here: a second copy in code is how the two drift apart.
            Log::error('Voice assistant prompt provider_voice_form is missing or inactive');

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
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
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        $data = json_decode($answer['content'], true);
        if (! is_array($data)) {
            Log::warning('Voice assistant returned unreadable JSON', [
                'content' => mb_substr((string) $answer['content'], 0, 300),
            ]);

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => false,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        $checked = $this->keepValid($form, $known, (array) ($data['fields'] ?? []), $asked);

        // One column of a table becomes the row it belongs to, and a member
        // saying there are no more finishes the table for good.
        $rows = $this->assembleRows($form, $known, $checked['fields'], $asked);
        $checked['fields'] = $rows['fields'];
        $finished = $rows['finished'] === null ? [] : [$rows['finished']];

        // This turn's answer wins. Written the other way round, a member who
        // said "no, the name is Pradeep Homestay, not just Homestay" had their
        // correction quietly dropped in favour of what it was correcting.
        $filled = $checked['fields'] + $known;

        // Which field comes next, decided here in the form's own order — the
        // model is not asked what to ask, only what was said.
        $ahead = $this->walk($form, $filled, array_merge($skipped, $here['passed'], $finished), $language);
        $next = $ahead['next'];

        // A few answers are worth reading back, and they are the ones a member
        // has no way of checking by ear.
        //
        // A registration number sounds like nothing, so a wrong one goes down
        // unnoticed and names somebody else's vehicle. The switches that
        // decide who pays are worse: they are a single word either way, and
        // the answers people actually give are not always the polarity of the
        // question. "No, fuel and tolls are included" can be read as "no fuel;
        // and tolls are included", which is the opposite, and it is not an
        // unreasonable reading — the sentence is genuinely ambiguous. No
        // wording of the question settles it, and asking the model harder only
        // moves which sentence it gets wrong.
        //
        // So it is not settled here. What it wrote is said back in words, and
        // a member who hears the wrong one can put it right while they are
        // still listening — instead of finding it on a rate card weeks later,
        // having quietly agreed to buy the diesel.
        $echo = [];
        if (isset($spec['echo']) && array_key_exists($asked, $checked['fields'])) {
            $value = $checked['fields'][$asked];
            $words = $spec['echo'][$language] ?? $spec['echo']['en'] ?? null;

            // A yes or no has no value worth printing — it has two readings,
            // and the whole point is to say which one was taken.
            $line = is_bool($value)
                ? ($words[$value ? 'true' : 'false'] ?? null)
                : (is_string($words) ? sprintf($words, $value) : null);

            if ($line) {
                $echo[] = $line;
            }
        }

        return [
            'fields' => $checked['fields'],
            // Boxes reached on the way here that have to be done by hand, and
            // the words to say about each — preceded by anything worth reading
            // back out of what was just recorded.
            'guidance' => array_merge($echo, $here['guidance'], $ahead['guidance']),
            // `$finished` is a table the member has just closed — "that is
            // all" — which the app must remember, or the next turn offers
            // another row and the offer never ends.
            'passed' => array_merge($here['passed'], $finished, $ahead['passed']),
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
        $spec = $this->specFor($form, $field, $known);

        // A row's opening question changes once there is a row: "do you sell
        // anything alongside this?" the first time, "anything else?" after.
        // Asked the first question a second time, a member reasonably answers
        // it again and adds what they have already added.
        if (isset($spec['q_more']) && $this->rowsOf($form, $field, $known) !== []) {
            return $spec['q_more'][$language] ?? $spec['q_more']['en'] ?? null;
        }

        return $spec['q'][$language] ?? $spec['q']['en'] ?? null;
    }

    /**
     * Turn one column's answer into the table the form actually holds.
     *
     * The model is asked for one thing at a time — a name, then a price — and
     * the form holds rows. This puts the answer into the row being built, and
     * says so when the member has said there are no more.
     *
     * @return array{fields:array<string,mixed>,finished:?string}
     */
    private function assembleRows(string $form, array $known, array $fields, ?string $asked): array
    {
        if ($asked === null || ! str_contains($asked, '.') || ! array_key_exists($asked, $fields)) {
            return ['fields' => $fields, 'finished' => null];
        }

        [$table, $column] = explode('.', $asked, 2);
        $rows = $this->rowsOf($form, $table, $known);
        $value = $fields[$asked];
        unset($fields[$asked]);

        // Whether there is another one. Yes opens an empty row, which is what
        // makes the next turn ask for its first column — nothing about this
        // conversation is stored, so the half-built row IS the memory of it.
        if ($column === 'more') {
            if ($value !== true) {
                return ['fields' => $fields, 'finished' => $table];
            }

            $rows[] = [];
            $fields[$table] = $rows;

            return ['fields' => $fields, 'finished' => null];
        }

        if ($rows === []) {
            $rows[] = [];
        }

        $rows[array_key_last($rows)][$column] = $value;
        $fields[$table] = $rows;

        return ['fields' => $fields, 'finished' => null];
    }

    /**
     * The specification for a field, whether it is a box on the form or one
     * column of a table on it.
     *
     * A table — the extras sold alongside a rate, the price tiers, the rooms,
     * the days of an itinerary — is not one question but a handful asked over
     * and over. Its parts are addressed as `addons.name`, and everything that
     * takes a field name goes through here so that a column answers to the
     * same questions a box does.
     *
     * @return array<string,mixed>
     */
    private function specFor(string $form, string $field, array $known = []): array
    {
        $schema = $this->schema($form, $known);

        if (! str_contains($field, '.')) {
            return $schema[$field] ?? [];
        }

        [$table, $column] = explode('.', $field, 2);
        $spec = $schema[$table] ?? [];

        // `.more` is the yes or no that opens a row, and it carries the
        // table's own label so a member sees which part of the form is meant.
        if ($column === 'more') {
            return ($spec['more'] ?? []) + ['label' => $spec['label'] ?? null, 'type' => 'bool'];
        }

        // A column the table does not have is not a field at all. Left to
        // return a label and nothing else, an invented column would look
        // enough like a field to be written into the form.
        if (! isset($spec['row'][$column])) {
            return [];
        }

        return $spec['row'][$column] + ['label' => $spec['label'] ?? null];
    }

    /**
     * The rows a table already holds, as the app last sent them.
     *
     * @return array<int,array<string,mixed>>
     */
    private function rowsOf(string $form, string $field, array $known): array
    {
        $table = explode('.', $field, 2)[0];
        $rows = $known[$table] ?? [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * Which column of a table is still waiting, or null when the last row is
     * complete and it is time to ask whether there is another.
     */
    private function unfilledColumn(array $spec, array $rows): ?string
    {
        if ($rows === []) {
            return null;
        }

        $last = end($rows);
        foreach (array_keys($spec['row'] ?? []) as $column) {
            $value = is_array($last) ? ($last[$column] ?? null) : null;
            if ($value === null || $value === '' || $value === []) {
                return $column;
            }
        }

        return null;
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
        $spec = $this->specFor($form, $field, $known);
        if (! isset($spec['list'])) {
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
        return $this->specFor($form, $field, $known)['label'] ?? null;
    }

    /**
     * What each allowed value covers, in HCT's own words, or null when the
     * list carries no notes at all.
     *
     * The names on their own are a taxonomy, not an explanation. Told only
     * that "Workshops, Handicrafts, Local Knowledge & Storytelling" is one of
     * three things it may answer, the model read "I teach cooking to tourists"
     * as guiding — the words a member uses are nothing like the words the list
     * uses, and nothing said which was which. HCT already writes a note beside
     * each value; sending it for the one field being asked costs a handful of
     * tokens and is the difference between a member being understood and being
     * asked again.
     *
     * Every value is listed whether or not it has a note. Sending only the
     * ones that do would quietly weight the answer towards them.
     */
    public function meaningsFor(array $field): ?string
    {
        if (! isset($field['list'])) {
            return null;
        }

        $rows = SystemList::ofType($field['list'])->get(['name', 'description']);

        if ($rows->isEmpty() || $rows->every(fn ($row) => trim((string) $row->description) === '')) {
            return null;
        }

        return $rows
            ->map(fn ($row) => trim((string) $row->description) !== ''
                ? sprintf('"%s" — %s', $row->name, trim($row->description))
                : sprintf('"%s"', $row->name))
            ->implode("\n");
    }

    /**
     * Which of the allowed values the model meant, or null if none of them.
     *
     * Case and spacing are the model's to get wrong; the value itself is not,
     * so anything matched here is stored exactly as HCT spells it.
     *
     * Some of HCT's values carry a code in front — "MAP - Breakfast + one
     * meal", "Cat D - Basic/Homestay" — and a model asked for one of those
     * naturally answers with the code alone. On a real phone a host said
     * "breakfast plus one meal", the model correctly answered "MAP", and the
     * answer was thrown away for not being the whole string; the host ended up
     * having to say "MAP" out loud, which is the one thing they should never
     * need to know. So either half of such a value is recognised — but only
     * when it names exactly one of them, because half a name that fits two
     * values names neither.
     */
    private function matchOption(array $allowed, mixed $value): ?string
    {
        $said = mb_strtolower(trim((string) $value));
        if ($said === '') {
            return null;
        }

        foreach ($allowed as $option) {
            if ($said === mb_strtolower($option)) {
                return $option;
            }
        }

        $byHalf = [];
        foreach ($allowed as $option) {
            if (! str_contains($option, ' - ')) {
                continue;
            }
            [$code, $rest] = explode(' - ', $option, 2);
            if ($said === mb_strtolower(trim($code)) || $said === mb_strtolower(trim($rest))) {
                $byHalf[] = $option;
            }
        }

        return count($byHalf) === 1 ? $byHalf[0] : null;
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
        // The valleys HECO works in. Not a SystemList — they are records with
        // an id, and the form stores the id — so the member is offered the
        // names and what they choose is turned back into an id in keepValid().
        if (($field['source'] ?? null) === 'regions') {
            return $this->regions()->pluck('name')->values()->all();
        }

        return null;
    }

    /** The regions on offer, read once per turn rather than per field. */
    private function regions(): \Illuminate\Support\Collection
    {
        return \App\Models\Region::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Whether a field may be passed over.
     *
     * Nearly all of them may. The one that decides the shape of the rest — what
     * kind of service this is — may not: skipping it leaves nothing to ask, and
     * the sheet used to announce "that is everything I can ask about" over an
     * empty form. It is refused server-side either way; this is so the app can
     * stop offering a button that cannot do anything, which is how a member
     * came to press Skip and watch the same question come straight back with
     * no word about why.
     */
    public function skippable(string $form, string $field, array $known = []): bool
    {
        return $this->specFor($form, $field, $known)['skippable'] ?? true;
    }

    /**
     * The next question, and a word about everything passed to reach it.
     *
     * Some boxes on the form cannot be filled by talking — photographs, a map
     * pin, a registration number a machine mishears, a table of extras with a
     * price against each. Left silently out, a member finishes the
     * conversation believing the form is finished too, and only the save
     * button disagrees. So they are kept in the running order and announced
     * as they are reached: this one is yours, here is the next question.
     *
     * `passed` names them so the app can put them behind it. Nothing about
     * this conversation is stored here, and without that the same box would
     * be announced again on every turn for the rest of the form.
     *
     * @return array{next:?string,guidance:array<int,string>,passed:array<int,string>}
     */
    public function walk(string $form, array $known, array $skipped, string $language): array
    {
        $guidance = [];
        $passed = [];

        while (true) {
            $next = $this->nextField($form, $known, array_merge($skipped, $passed));
            if ($next === null) {
                return ['next' => null, 'guidance' => $guidance, 'passed' => $passed];
            }

            $spec = $this->schema($form, $known)[$next] ?? [];
            if (! isset($spec['manual'])) {
                return ['next' => $next, 'guidance' => $guidance, 'passed' => $passed];
            }

            $guidance[] = $spec['manual'][$language] ?? $spec['manual']['en'] ?? '';
            $passed[] = $next;
        }
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

            // A table is never "filled": there is always the possibility of
            // one more row. It is finished when the member says so, which
            // arrives here as the table's name among the skipped.
            if (isset($field['row'])) {
                $rows = $this->rowsOf($form, $key, $known);
                $column = $this->unfilledColumn($field, $rows);

                // A row half said — a name with no price against it — is
                // finished before another is offered.
                return $column === null ? $key . '.more' : $key . '.' . $column;
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
            // A column of a table answers to the same rules as a box on the
            // form; it is only addressed differently.
            $field = str_contains((string) $key, '.')
                ? ($this->specFor($form, (string) $key, $known) ?: null)
                : ($schema[$key] ?? null);

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

            // Months are numbers on this form, not names: a member says
            // "March to June" and the four boxes hold 3, 4, 5 and 6. Anything
            // outside the twelve is dropped rather than stored as a month that
            // does not exist.
            if (($field['type'] ?? 'string') === 'months') {
                $said = is_array($value)
                    ? $value
                    : (preg_split('/\s*(?:,|and|और|aur|to|से|se)\s*/ui', (string) $value) ?: []);

                $months = [];
                foreach ($said as $one) {
                    $n = (int) preg_replace('/\D+/', '', (string) $one);
                    if ($n >= 1 && $n <= 12) {
                        $months[] = $n;
                    }
                }

                $months = array_values(array_unique($months));
                sort($months);
                if ($months === []) {
                    $rejected[] = (string) $key;
                    continue;
                }

                $fields[$key] = $months;
                continue;
            }

            $allowed = $this->allowedFor($field);

            // A box that holds several of the list at once, not one of them —
            // the languages a guide works in. Asked "which languages can you
            // guide in", nobody names one, and read as a single value the
            // whole answer was thrown away for not being on the list.
            if ($allowed !== null && ($field['type'] ?? 'string') === 'multi') {
                $said = is_array($value)
                    ? $value
                    : (preg_split('/\s*(?:,|and|और|aur)\s*/ui', (string) $value) ?: []);

                $kept = [];
                foreach ($said as $one) {
                    $option = $this->matchOption($allowed, $one);
                    if ($option !== null) {
                        $kept[] = $option;
                    }
                }

                $kept = array_values(array_unique($kept));
                if ($kept === []) {
                    $rejected[] = (string) $key;
                    continue;
                }

                $fields[$key] = $kept;
                continue;
            }

            if ($allowed !== null) {
                // Case and spacing are the model's to get wrong; the value
                // itself is not. Match loosely, store exactly.
                $match = $this->matchOption($allowed, $value);
                if ($match === null) {
                    $rejected[] = (string) $key;
                    continue;
                }

                // A region is chosen by name and stored by id. Said aloud,
                // "Tirthan Valley" is what a member means; what the form holds
                // is the number beside it.
                if (($field['source'] ?? null) === 'regions') {
                    $region = $this->regions()->firstWhere('name', $match);
                    if (! $region) {
                        $rejected[] = (string) $key;
                        continue;
                    }
                    $fields[$key] = $region->id;
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
