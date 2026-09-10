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
 * exchanges. What each option covers — HCT's note beside it, which is the bulk
 * of it — is sent only for the field being asked about. The other boxes in the
 * turn carry their options by name alone, which is what a member answering one
 * of them early needs and costs almost nothing.
 */
class VoiceAssistantService
{
    /**
     * How many other boxes a member may be heard answering in one breath.
     *
     * Four while only free text and numbers counted, which was enough to reach
     * past them; now that a list or a yes-or-no box counts too there are more
     * of them in the way, and at four the ones that matter — whether a driver
     * comes with the vehicle, whether they speak English — sat just outside and
     * were never offered. Ten because the experience form is long, and two of
     * the ten are often spent on the boxes that are listened for wherever they
     * sit. Measured: the option lists are three to eleven short names, so the
     * whole of this is about a twentieth of a turn.
     */
    private const EXTRAS_MAX = 10;

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
                // Six values, and the question named five: 'other' was left
                // out of both tongues. A member who launders clothes was told
                // their work "इन विकल्पों में नहीं आती" — and it did not, because
                // the only door it could go through was the one not mentioned.
                'ask' => 'whether they are offering a place to stay, a vehicle, a guide, an activity, something they rent out, or some other service',
                'q' => [
                    'hi' => 'आप क्या दे रहे हैं: रहने की जगह, गाड़ी, गाइड, कोई गतिविधि, किराये पर कुछ सामान, या कोई और सेवा?',
                    'en' => 'What are you offering: a place to stay, a vehicle, a guide, an activity, something you rent out, or some other service?',
                ],
                'type' => 'string',
                'only' => ['accommodation', 'transport', 'guide', 'activity', 'rental', 'other'],
                // What the codes above are called when they are shown. The
                // column stores 'accommodation'; a member looking for the
                // choices is looking for "a place to stay".
                'words' => [
                    'hi' => ['रहने की जगह', 'गाड़ी', 'गाइड', 'कोई गतिविधि', 'किराये पर सामान', 'कुछ और'],
                    'en' => ['A place to stay', 'A vehicle', 'A guide', 'An activity', 'Something you rent out', 'Something else'],
                ],
                'skippable' => false,
            ],
        ];

        // Until they have said what they offer there is nothing sensible to ask
        // next: every field below belongs to one kind of service.
        return $common + match ($known['service_type'] ?? null) {
            'accommodation' => [
                'category' => ['label' => 'Property name', 'ask' => 'what their place is called', 'q' => ['hi' => 'आपकी जगह का नाम क्या है?', 'en' => 'What is your place called?'], 'type' => 'string'],
                // asked_only: how good the rooms are is not something the word
                // "homestay" settles. See mineAgain().
                'comfort_tier' => ['label' => 'Comfort tier', 'ask' => 'what sort of place it is', 'q' => ['hi' => 'यह किस तरह की जगह है?', 'en' => 'What sort of place is it?'], 'type' => 'string', 'list' => 'accommodation_category', 'asked_only' => true],
                'room_category' => ['label' => 'Room category', 'ask' => 'what kind of room they are pricing', 'q' => ['hi' => 'आप किस तरह के कमरे का दाम बता रहे हैं?', 'en' => 'Which kind of room are you pricing?'], 'type' => 'string', 'list' => 'room_category'],
                'total_rooms' => ['label' => 'Total rooms', 'ask' => 'how many rooms of that kind they have', 'q' => ['hi' => 'ऐसे कितने कमरे हैं आपके पास?', 'en' => 'How many such rooms do you have?'], 'type' => 'int'],
                'price' => ['label' => 'Rate per night (Rs)', 'ask' => 'what one room costs for a night', 'q' => ['hi' => 'एक रात का कितना लेते हैं?', 'en' => 'What do you charge for a night?'], 'type' => 'number'],
                'meal_plan' => ['label' => 'Meal plan', 'ask' => 'which meals are included in that price', 'q' => ['hi' => 'इस दाम में कौन सा खाना शामिल है?', 'en' => 'Which meals are included in that price?'], 'type' => 'string', 'list' => 'meal_plan'],
                'default_occupancy' => ['label' => 'Default occupancy', 'ask' => 'whether the room is normally sold as a single, a double, and so on', 'q' => ['hi' => 'यह कमरा आम तौर पर किस हिसाब से दिया जाता है: सिंगल, डबल या कोई और?', 'en' => 'How is this room normally sold: as a single, a double, or something else?'], 'type' => 'string', 'list' => 'room_occupancy'],
                // Nobody says their own coordinates aloud, and a misheard
                // digit puts the place in the wrong valley — so this is said
                // rather than asked.
                'coordinates' => ['label' => 'Latitude & longitude', 'manual' => ['hi' => 'नक्शे पर जगह का निशान, अक्षांश और देशांतर, आपको फ़ॉर्म में खुद भरना होगा। बोलकर नहीं हो सकता, और एक अंक भी ग़लत सुना गया तो जगह दूसरी घाटी में चली जाएगी।', 'en' => 'The map pin, latitude and longitude, you will need to fill in yourself. It cannot be spoken, and one digit heard wrong puts your place in the wrong valley.']],
                'guest_capacity' =>['label' => 'Guests it sleeps', 'ask' => 'how many guests the place sleeps in all', 'q' => ['hi' => 'कुल कितने मेहमान रुक सकते हैं?', 'en' => 'How many guests can stay in all?'], 'type' => 'int'],
                'seasonality_notes' => ['label' => 'Seasonality', 'ask' => 'which months they take guests, and which they do not', 'q' => ['hi' => 'साल के किन महीनों में मेहमान आ सकते हैं?', 'en' => 'Which months of the year can guests come?'], 'type' => 'string', 'accrues' => true],
                'photos' => ['label' => 'Photos', 'manual' => ['hi' => 'तस्वीरें आपको खुद जोड़नी होंगी, फ़ॉर्म में Photos वाले हिस्से से। यात्री सबसे पहले वही देखता है, इसलिए दो-तीन अच्छी तस्वीरें ज़रूर लगाइए।', 'en' => 'Photos you will need to add yourself, from the Photos part of the form. They are the first thing a traveller looks at, so put two or three good ones in.']],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
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
                        'hi' => 'गाड़ी का नंबर बताइए, धीरे-धीरे, एक-एक अक्षर और अंक।',
                        'en' => 'What is the vehicle\'s registration number? Say it slowly, letter by letter.',
                    ],
                    'type' => 'string',
                    'echo' => [
                        'hi' => 'मैंने लिखा है: %s. अगर ग़लत है तो फ़ॉर्म में ठीक कर लीजिए।',
                        'en' => 'I have written: %s. If that is wrong, correct it in the form.',
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
                    // "Yes I drive it myself" was read as false — no driver
                    // hired, therefore none included — when it is the plainest
                    // yes there is. Who the driver is was never the question.
                    'ask' => 'whether somebody drives the vehicle as part of this rate. True when a driver comes with it, the owner driving it themselves included; false only when the traveller has to find one',
                    'q' => ['hi' => 'क्या इस दाम में ड्राइवर शामिल है?', 'en' => 'Does that rate include a driver?'],
                    'type' => 'bool',
                    'echo' => [
                        'hi' => ['true' => 'ठीक है, ड्राइवर दाम में शामिल है।', 'false' => 'ठीक है, ड्राइवर दाम में शामिल नहीं है।'],
                        'en' => ['true' => 'Noted, the driver comes with the rate.', 'false' => 'Noted, the driver is not included in the rate.'],
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
                        'hi' => ['true' => 'ठीक है, तेल और टोल अलग से लगेंगे।', 'false' => 'ठीक है, तेल और टोल दाम में शामिल हैं।'],
                        'en' => ['true' => 'Noted, fuel and tolls are charged on top.', 'false' => 'Noted, fuel and tolls are included in the rate.'],
                    ],
                ],
                'vehicle_photos' => ['label' => 'Vehicle photos', 'manual' => ['hi' => 'गाड़ी की तस्वीरें आपको खुद जोड़नी होंगी, बोलकर नहीं हो सकतीं।', 'en' => 'Photos of the vehicle you will need to add yourself, they cannot be spoken.']],
                'price_per_km_plains' =>['label' => 'Cost per km: plains (Rs)', 'ask' => 'what a kilometre costs on flat roads', 'q' => ['hi' => 'मैदान में एक किलोमीटर का कितना लगता है?', 'en' => 'What does a kilometre cost on the plains?'], 'type' => 'number'],
                'price_per_km_hills' => ['label' => 'Cost per km: hills (Rs)', 'ask' => 'what a kilometre costs in the hills', 'q' => ['hi' => 'पहाड़ में एक किलोमीटर का कितना लगता है?', 'en' => 'What does a kilometre cost in the hills?'], 'type' => 'number'],
                'vehicle_count' => ['label' => 'Number of vehicles', 'ask' => 'how many such vehicles they run', 'q' => ['hi' => 'ऐसी कितनी गाड़ियाँ हैं आपके पास?', 'en' => 'How many such vehicles do you have?'], 'type' => 'int'],
                'ac_available' => [
                    'label' => 'Air conditioning available',
                    'ask' => 'whether the vehicle has air conditioning',
                    'q' => ['hi' => 'क्या गाड़ी में एसी है?', 'en' => 'Does the vehicle have air conditioning?'],
                    'type' => 'bool',
                    'echo' => [
                        'hi' => ['true' => 'ठीक है, गाड़ी में एसी है।', 'false' => 'ठीक है, गाड़ी में एसी नहीं है।'],
                        'en' => ['true' => 'Noted, the vehicle has air conditioning.', 'false' => 'Noted, the vehicle has no air conditioning.'],
                    ],
                ],
                'ac_extra_cost' => ['label' => 'Extra cost for AC (Rs)', 'ask' => 'what air conditioning costs on top, if anything', 'q' => ['hi' => 'एसी का अलग से कितना लगता है?', 'en' => 'What does air conditioning cost on top?'], 'type' => 'number'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
            ],
            'guide' => [
                // The form's own control here is a picker over HCT's guide
                // types, not a free-text box. Treated as free text, whatever
                // the member said was stored and then shown as an empty
                // "Select" — a value they could neither see nor correct.
                //
                // The question has to ask for what the list actually holds.
                // "What kind of guiding do you do?" invites the subject — birds,
                // forest, temples — and the list has nothing of the sort in it:
                // it grades a guide by language and certificate. Asked the old
                // way, "पक्षी और जंगल के बारे में बताता हूँ" was filed as Local
                // Guide in Hindi and Certified/Expert in English, two guesses at
                // the same sentence, while the thing they actually told us went
                // nowhere. Specialties is the box that wants the subject, and it
                // is asked further down in those words.
                'category' => ['label' => 'Guide type / language', 'ask' => 'which sort of guide they are: a local one, an English-speaking one, or a certified or expert one', 'q' => ['hi' => 'आप स्थानीय गाइड हैं, अंग्रेज़ी बोलने वाले गाइड, या प्रमाणित या विशेषज्ञ गाइड?', 'en' => 'Are you a local guide, an English-speaking guide, or a certified or expert one?'], 'type' => 'string', 'list' => 'guide_preference', 'except' => ['No Guide'], 'asked_only' => true],
                'price' => ['label' => 'Rate per day (Rs)', 'ask' => 'what they charge for a day', 'q' => ['hi' => 'एक दिन का कितना लेते हैं?', 'en' => 'What do you charge for a day?'], 'type' => 'number'],
                // Optional details, in the order the form's own section has them.
                'specialties' => ['label' => 'Specialties', 'ask' => 'what they guide: birds, forest, culture, and so on', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string', 'accrues' => true],
                'wage_multi_day' => ['label' => 'Rate per day: multi-day with night stay (Rs)', 'ask' => 'what they charge a day on a trip where they stay the night', 'q' => ['hi' => 'जिस काम में रात रुकना पड़े, उसका एक दिन का कितना लेते हैं?', 'en' => 'On a trip where you stay the night, what do you charge for a day?'], 'type' => 'number'],
                'languages' => ['label' => 'Other languages', 'ask' => 'which languages they can guide in', 'q' => ['hi' => 'आप किन-किन भाषाओं में गाइड कर सकते हैं?', 'en' => 'Which languages can you guide in?'], 'type' => 'multi', 'list' => 'language'],
                'speaks_english' => ['label' => 'Speaks English', 'ask' => 'whether they speak English', 'q' => ['hi' => 'क्या आप अंग्रेज़ी बोल लेते हैं?', 'en' => 'Do you speak English?'], 'type' => 'bool'],
                'is_certified' => ['label' => 'Certified guide', 'ask' => 'whether they hold a guiding certificate', 'q' => ['hi' => 'क्या आपके पास गाइड का कोई सर्टिफिकेट है?', 'en' => 'Do you hold a guiding certificate?'], 'type' => 'bool'],
                'has_first_aid' => ['label' => 'First-aid trained', 'ask' => 'whether they are trained in first aid', 'q' => ['hi' => 'क्या आपने फर्स्ट-एड की ट्रेनिंग ली है?', 'en' => 'Have you had first-aid training?'], 'type' => 'bool'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
            ],
            'activity' => [
                // Also a picker in the form, over HCT's activity types.
                'category' => ['label' => 'Activity type', 'ask' => 'what kind of activity it is', 'q' => ['hi' => 'यह किस तरह की गतिविधि है?', 'en' => 'What kind of activity is it?'], 'type' => 'string', 'list' => 'activity_type'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what it costs', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'whether that price is per person or per group', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string', 'list' => 'activity_unit'],
                // Optional details, in the order the form's own section has them.
                'min_group' => ['label' => 'Min group size', 'ask' => 'the smallest group they will take', 'q' => ['hi' => 'कम से कम कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the smallest group you will take?'], 'type' => 'int'],
                'max_group' => ['label' => 'Max group size', 'ask' => 'the largest group they will take', 'q' => ['hi' => 'ज़्यादा से ज़्यादा कितने लोगों का समूह ले सकते हैं?', 'en' => 'What is the largest group you will take?'], 'type' => 'int'],
                'specialties' => ['label' => 'Specialties', 'ask' => 'what the activity involves', 'q' => ['hi' => 'आप किस चीज़ के बारे में बताते हैं?', 'en' => 'What is it that you show people?'], 'type' => 'string', 'accrues' => true],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
            ],
            // The form offers this too, and without an arm of its own the
            // schema held nothing but service_type — which `known` already
            // answered — so the assistant said "that is everything I can ask
            // about" over a completely empty form.
            'other' => [
                'category' => ['label' => 'Service name', 'ask' => 'what to call this service', 'q' => ['hi' => 'इस सेवा को क्या नाम दें?', 'en' => 'What should this service be called?'], 'type' => 'string'],
                'price' => ['label' => 'Rate (Rs)', 'ask' => 'what they charge', 'q' => ['hi' => 'इसका दाम कितना है?', 'en' => 'What does it cost?'], 'type' => 'number'],
                'unit' => ['label' => 'Unit', 'ask' => 'what that price is for: per person, per day, per piece, whatever they charge by', 'q' => ['hi' => 'यह दाम किस हिसाब से है?', 'en' => 'What is that price for?'], 'type' => 'string'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
            ],
            'rental' => [
                'rental_item' => ['label' => 'Item on rent', 'ask' => 'what they rent out', 'q' => ['hi' => 'आप किराये पर क्या देते हैं?', 'en' => 'What do you rent out?'], 'type' => 'string'],
                'price' => ['label' => 'Charges per day (Rs)', 'ask' => 'what it costs to rent for a day', 'q' => ['hi' => 'एक दिन का किराया कितना है?', 'en' => 'What does it cost to rent for a day?'], 'type' => 'number'],
                                'security_deposit' => ['label' => 'Security deposit (Rs)', 'ask' => 'what deposit they hold, if any', 'q' => ['hi' => 'कितनी रकम जमानत के तौर पर रखते हैं?', 'en' => 'What deposit do you hold?'], 'type' => 'number'],
                'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'description' => ['label' => 'Internal note', 'ask' => 'a note for HECO about this rate, if they want to leave one', 'q' => ['hi' => 'HECO के लिए कोई नोट लिखना चाहेंगे?', 'en' => 'Any note you would like to leave for HECO?'], 'type' => 'string', 'accrues' => true],
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
            // Written about the experience, for a traveller to read. Told only
            // to describe it, the model wrote down what the MEMBER had just
            // said about themselves: "I take people trekking through forest and
            // villages, I guide them" became the blurb, in the first person in
            // Hindi and the third in English. Neither is a line a traveller
            // would read on a listing.
            'short_description' => ['label' => 'Short description', 'ask' => 'a sentence or two describing the experience to a traveller, written about the experience and never about the member: "A three day walk through forest and old villages", never "I take people trekking" or "They take people trekking"', 'q' => ['hi' => 'एक-दो लाइन में बताइए, यात्री को इसमें क्या मिलेगा?', 'en' => 'In a line or two, what does a traveller get from it?'], 'type' => 'string'],
            'long_description' => ['label' => 'Long description', 'ask' => 'the fuller story of the experience', 'q' => ['hi' => 'इस अनुभव की पूरी बात बताइए।', 'en' => 'Tell me the fuller story of this experience.'], 'type' => 'string', 'accrues' => true],
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
                    'hi' => 'इसमें कितना समय लगता है: कुछ घंटे, पूरा दिन, या कई दिन?',
                    'en' => 'How long does it take: a few hours, a whole day, or several days?',
                ],
                'type' => 'string',
                'only' => ['less_than_day', 'single_day', 'multi_day'],
                // The codes are how the column files it; these are how
                // anybody would say it.
                'words' => [
                    'hi' => ['एक दिन से कम', 'एक पूरा दिन', 'एक से ज़्यादा दिन'],
                    'en' => ['Less than a day', 'A single day', 'More than one day'],
                ],
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
            // asked_only: a grade, never to be read out of a name. Asked what
            // their place was called, a member said "पहाड़ी होमस्टे" and it went
            // in here as Cat D while the name box stayed empty; the next thing
            // they said, the region, became the name. See mineAgain().
            //
            // The four values are hotel grades, and a trek where travellers
            // sleep in tents is none of them. Nothing here can invent a value
            // HCT does not keep, so the question says instead that it may be
            // left, which is true of it and was not being said.
            'accommodation_category' => ['label' => 'Accommodation category', 'ask' => 'what sort of place travellers stay in, and that they may leave it where they camp or where none of the four fits', 'q' => ['hi' => 'यात्री किस तरह की जगह पर रुकते हैं? अगर तंबू में रुकते हैं या इनमें से कोई ठीक न बैठे तो इसे छोड़ दीजिए।', 'en' => 'What sort of place do travellers stay in? If they camp, or none of these fits, you can leave this one.'], 'type' => 'string', 'list' => 'accommodation_category', 'asked_only' => true],
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
                'words' => [
                    'hi' => ['आसान', 'थोड़ा मुश्किल', 'मुश्किल', 'बहुत मुश्किल'],
                    'en' => ['Easy', 'Moderate', 'Challenging', 'Extreme'],
                ],
            ],
            'fitness_requirements' => ['label' => 'Fitness requirements', 'ask' => 'how fit a traveller needs to be', 'q' => ['hi' => 'यात्री का शरीर कितना चलने-फिरने लायक होना चाहिए?', 'en' => 'How fit does a traveller need to be for this?'], 'type' => 'string'],
            'weather_dependency' => ['label' => 'Weather dependency', 'ask' => 'how the weather affects it', 'q' => ['hi' => 'मौसम का इस पर क्या असर पड़ता है?', 'en' => 'How does the weather affect it?'], 'type' => 'string'],
            'cultural_sensitivities' => ['label' => 'Cultural sensitivities', 'ask' => 'anything a visitor should be careful about', 'q' => ['hi' => 'यात्री को किन बातों का ध्यान रखना चाहिए?', 'en' => 'Is there anything a visitor should be careful about?'], 'type' => 'string'],
            'environmental_constraints' => ['label' => 'Environmental constraints', 'ask' => 'anything about the place that limits how many people can come, or when', 'q' => ['hi' => 'जगह की वजह से कोई पाबंदी है: कितने लोग आ सकते हैं, या कब?', 'en' => 'Does the place itself limit how many can come, or when?'], 'type' => 'string'],
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
            'seasonality_notes' => ['label' => 'Seasonality notes', 'ask' => 'anything else about the seasons here', 'q' => ['hi' => 'मौसम के बारे में और कुछ बताना चाहेंगे?', 'en' => 'Anything else about the seasons here?'], 'type' => 'string', 'accrues' => true],
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
                        'ask' => 'what that day includes: meals, a place to stay, a guide, transport',
                        'q' => ['hi' => 'उस दिन में क्या-क्या शामिल है: खाना, रहना, गाइड, आना-जाना?', 'en' => 'What does that day include: meals, a bed, a guide, transport?'],
                        'type' => 'multi',
                        'list' => 'day_inclusion',
                    ],
                ],
            ],
            'addons' => [
                    'label' => 'Add-ons',
                    'more' => [
                        'q' => [
                            'hi' => 'इसके साथ कोई अलग चीज़ भी बेचते हैं: जैसे एक गद्दा, या स्टेशन से लिवाना?',
                            'en' => 'Do you sell anything alongside this: an extra mattress, a pickup from the station?',
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
                'hi' => 'तस्वीरें आपको खुद जोड़नी होंगी: एक कार्ड वाली तस्वीर और बाकी गैलरी में। बोलकर तस्वीर नहीं बनती, और यात्री सबसे पहले वही देखता है।',
                'en' => 'Photos you will need to add yourself, one for the card and the rest in the gallery. A microphone does not take pictures, and they are the first thing a traveller looks at.',
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
            'health_notes' => ['label' => 'Health notes', 'ask' => 'anything about health a traveller should know', 'q' => ['hi' => 'सेहत के बारे में यात्री को कुछ बताना ज़रूरी है?', 'en' => 'Is there anything about health a traveller should know?'], 'type' => 'string', 'accrues' => true],
            'connectivity_notes' => ['label' => 'Connectivity notes', 'ask' => 'whether there is phone signal or internet there', 'q' => ['hi' => 'वहाँ फ़ोन का नेटवर्क या इंटरनेट मिलता है?', 'en' => 'Is there phone signal or internet there?'], 'type' => 'string', 'accrues' => true],
            'cultural_etiquette' => ['label' => 'Cultural etiquette', 'ask' => 'how a visitor should behave with local people', 'q' => ['hi' => 'यात्री को यहाँ के लोगों के साथ कैसे पेश आना चाहिए?', 'en' => 'How should a visitor behave with local people?'], 'type' => 'string'],
        ];

        $operational = [
            'operational_risks' => ['label' => 'Operational risks', 'ask' => 'what could go wrong on the day', 'q' => ['hi' => 'उस दिन क्या-क्या गड़बड़ हो सकती है?', 'en' => 'What could go wrong on the day?'], 'type' => 'string'],
            'past_issues' => ['label' => 'Past issues', 'ask' => 'anything that has gone wrong before', 'q' => ['hi' => 'पहले कभी कुछ गड़बड़ हुई है? क्या?', 'en' => 'Has anything gone wrong before? What?'], 'type' => 'string'],
            'backup_options' => ['label' => 'Backup options', 'ask' => 'what they do instead when it cannot go ahead', 'q' => ['hi' => 'अगर यह न हो पाए तो उसकी जगह क्या करते हैं?', 'en' => 'If this cannot go ahead, what do you do instead?'], 'type' => 'string'],
            'emergency_notes' => ['label' => 'Emergency notes', 'ask' => 'what happens in an emergency, and who is called', 'q' => ['hi' => 'आपात स्थिति में क्या करते हैं, और किसे बुलाते हैं?', 'en' => 'In an emergency, what do you do and who do you call?'], 'type' => 'string', 'accrues' => true],
        ];

        // A stay is not a scheduled thing. The app's own form drops Duration,
        // Requirements and Costing the moment the category is a stay, and puts
        // rooms and beds in their place — so asking a homestay owner how hard
        // their experience is, and never asking how many rooms they have, both
        // stop here rather than being tidied up afterwards.
        if ($stay) {
            return $basic + $inclusions + $location + $seasonality + [
                // listen_always: said in the first breath and asked twenty boxes
                // later. "चार कमरे हैं और आठ लोग रुक सकते हैं" went nowhere at all,
                // in both tongues, because nothing that far down is ever within
                // reach of the sentence in front of us.
                'total_rooms' => ['label' => 'Rooms', 'ask' => 'how many rooms the place has', 'q' => ['hi' => 'इस जगह में कितने कमरे हैं?', 'en' => 'How many rooms does the place have?'], 'type' => 'int', 'listen_always' => true],
                'total_guests' => ['label' => 'Guests it sleeps', 'ask' => 'how many guests it sleeps in all', 'q' => ['hi' => 'कुल कितने मेहमान रुक सकते हैं?', 'en' => 'How many guests can stay in all?'], 'type' => 'int', 'listen_always' => true],
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
        $out = $this->exchange($form, $known, $said, $language, $skipped);

        foreach (['note', 'reply'] as $key) {
            if (is_string($out[$key] ?? null)) {
                $out[$key] = $this->inTongue($out[$key], $language) ?: null;
            }
        }

        if (is_array($out['guidance'] ?? null)) {
            $out['guidance'] = array_values(array_filter(array_map(
                fn ($line) => $this->inTongue((string) $line, $language),
                $out['guidance'],
            )));
        }

        return $out;
    }

    /**
     * Everything a member hears, in a tongue they read.
     *
     * A Hindi answer about security deposits ended "...लिख सकते हैं। ಉದ": the
     * model reached for the word "example", found it in Kannada, and got two
     * syllables out before the reply ran out of room. Nobody in the valley
     * reads Kannada, and there is no telling which script it will wander into
     * next, so rather than name them the rule is put the other way round —
     * Latin and Devanagari and the punctuation both share, and nothing else.
     * An English reply keeps Devanagari too: a member may be told a Hindi word
     * back, and that is not the failure this is for.
     *
     * The wrong full stop is the same fault in miniature and has been seen
     * before, so the handful of CJK marks are turned into the ones this form
     * uses rather than dropped, which would run two sentences together.
     */
    private function inTongue(string $text, string $language): string
    {
        // The CJK marks carry their own spacing, so each is replaced by the
        // mark and the space this form would have written, and doubled spaces
        // are squeezed after. Only runs of spaces: a line break in a note is
        // the writer's and stays.
        $text = (string) preg_replace('/ {2,}/u', ' ', strtr($text, [
            '。' => ($language === 'hi' ? '। ' : '. '),
            '，' => ', ', '、' => ', ', '；' => '; ', '：' => ': ',
            '？' => '? ', '！' => '! ',
        ]));

        $clean = preg_replace('/[^\p{Latin}\p{Devanagari}\p{Common}\p{Inherited}]+/u', '', $text);

        if ($clean === null || $clean === $text) {
            return rtrim($text);
        }

        // Whatever it was in the middle of when it wandered, it is not a
        // sentence any more: a trailing comma or half a clause is worse to
        // hear than a clean stop.
        return rtrim(trim((string) preg_replace('/\s+/u', ' ', $clean)), " ,;:-");
    }

    private function exchange(
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
                'reply' => $this->phrase($this->questionFor($form, $asked, $language, $known), $language, (string) $this->labelFor($form, $asked, $known), $this->choicesFor($form, $asked, $known, $language), $this->meaningsFor($this->specFor($form, $asked, $known), $language)),
                'asked' => $asked,
                'label' => $this->labelFor($form, $asked, $known),
                'choices' => $this->choicesFor($form, $asked, $known, $language),
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
        $meanings = $this->meaningsFor($spec, $language);

        $prompt = app(PromptBuilderService::class)->build('provider_voice_form', [
            // The question in the member's own words. Without it the model was
            // reading an answer against a field name and had no way to tell an
            // answer to THIS question from a sentence about something else —
            // so anything said became the value of whatever was open.
            // Which tongue the line back must be in. Left to work it out from
            // the transcript, it drifted — "Pradeep Homestay noted" in the
            // middle of a Hindi conversation, "समझ गया" in the middle of an
            // English one — and a word of the wrong language is the surest
            // sign of a machine there is.
            'reply_in' => $language === 'hi' ? 'Hindi' : 'English',
            // What shape the line back should take THIS time.
            //
            // Asking the model to vary does not make it vary: it answers at
            // temperature 0.10, which is what keeps the values it reads out
            // reliable and is also why it chose the same six words every turn.
            // Raising the temperature to loosen its phrasing would loosen its
            // reading of prices along with it.
            //
            // So the variation is asked for outright, and it rotates on how
            // much of the form is done — no memory needed, and a member goes
            // through the shapes rather than hearing one of them forty times.
            'tone' => $this->toneFor(count($known)),
            // The headings of the boxes already answered. Asked to name one a
            // member wants to change without being told what there is to name,
            // the model named nothing at all — the instruction was abstract and
            // it had no list to point at.
            'filled' => $this->filledLabels($form, $known) ?: '(nothing yet)',
            // The question that will almost certainly come next, so the model
            // can put it in its own words rather than have the written one
            // appended to whatever it says.
            //
            // Almost certainly, not certainly: an answer can change the shape
            // of the form under it — saying what kind of service this is, or
            // that a stay is a stay — and a member who declines moves somewhere
            // else again. When the guess turns out wrong the written question
            // is used instead, so the worst case is the wording it has always
            // had rather than a question about the wrong box.
            'next_question' => ($guessed = $this->likelyNext($form, $known, $asked, $skipped)) === null
                ? ''
                : ($this->questionFor($form, $guessed, $language, $known) ?? ''),
            'question' => $this->questionFor($form, $asked, $language, $known) ?? '',
            // The shape matters as much as the meaning. Told only what the
            // field is about, the model wrote "at least two people" into a box
            // that holds a whole number, and the answer was thrown away.
            'asked' => sprintf(
                '%s: %s. This field holds %s.',
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
                    'months' => 'a JSON array of month numbers, 1 for January through 12 for December, with every month they name spelled out: a range like March to June is [3, 4, 5, 6]',
                    // The rule about English is in the system prompt too, and
                    // was quietly lost about half the time — a homestay named
                    // in Hindi went into the listing in Devanagari. It is
                    // repeated here, beside the field, where the model is
                    // looking when it decides what to write.
                    default => 'text, written in English whatever language they spoke',
                },
            ),
            'allowed' => $options
                ? json_encode($options, JSON_UNESCAPED_UNICODE)
                : '(none: this field takes free text)',
            // The notes carry their own heading rather than the template
            // carrying it, so a list with nothing written beside it leaves no
            // empty heading behind for the model to wonder about.
            'meanings' => $meanings === null ? '' : "\n\nWhat each of those covers:\n" . $meanings,
            // Boxes further down they may have answered in the same breath.
            'extras' => ($extras = $this->extrasFor($form, $known, $asked, $skipped))
                ? $this->extrasLines($extras, $known, $language)
                : '(none: only the field above)',
            'said' => $said,
        ]);

        if (! $prompt) {
            // The prompt row is missing. Say so plainly rather than improvising
            // one here: a second copy in code is how the two drift apart.
            Log::error('Voice assistant prompt provider_voice_form is missing or inactive');

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known, $language),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        $answer = $this->ai()->chat([
            ['role' => 'system', 'content' => $prompt['system_prompt']],
            ['role' => 'user', 'content' => $prompt['user_prompt']],
        ], [
            'groq_model' => $prompt['model'] ?: null,
            // Ignored by OpenAI, which takes its model from config: the four
            // prompt rows name a Groq model by its Groq name.
            'openai_model' => config('openai.model'),
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
                    'choices' => $this->choicesFor($form, $asked, $known, $language),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => true,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        $data = json_decode($answer['content'], true);
        if (! is_array($data)) {
            Log::warning('Voice assistant returned unreadable JSON', [
                'content' => mb_substr((string) $answer['content'], 0, 300),
            ]);

            return ['fields' => [], 'reply' => null, 'asked' => $asked, 'label' => $this->labelFor($form, $asked, $known),
                    'choices' => $this->choicesFor($form, $asked, $known, $language),
                    'done' => false, 'rejected' => [], 'note' => null, 'unavailable' => false,
                    'guidance' => $here['guidance'], 'passed' => $here['passed']];
        }

        // What the member hears before the next question. The questions
        // themselves are written down and never vary — which is what keeps the
        // conversation ordered and complete, and is also exactly why it reads
        // as a form being recited. This is the half that reacts: "Local guide,
        // noted", "पंद्रह सौ रुपये रोज़ — ठीक है". It costs a dozen words on a
        // turn that was being made anyway.
        //
        // It is only ever said, never stored, so there is nothing it can get
        // wrong beyond sounding odd — and it is cut short rather than trusted,
        // because a model given room to talk eventually asks its own question
        // and the member answers that instead of the one that follows.
        $say = trim((string) ($data['say'] ?? ''));
        $say = mb_strlen($say) > 160 ? '' : $say;

        // It runs straight into the question that follows it otherwise —
        // "fifteen hundred a day What is it that you show people?" — which
        // reads as one garbled sentence rather than two. The danda is Hindi's
        // full stop and counts as one.
        if ($say !== '' && ! preg_match('/[.!?।]$/u', $say)) {
            $say .= $language === 'hi' ? '।' : '.';
        }

        // The next question in the model's own words. A written question is
        // the same forty times over, which is most of what makes this sound
        // like a form being read out; the model asks the same thing differently
        // each time. What it may not do is ask something else, so it is only
        // used when it turns out to be about the box that actually came next,
        // and it is refused if it has grown into a speech.
        $ask = trim((string) ($data['ask'] ?? ''));
        $ask = mb_strlen($ask) > 200 ? '' : $ask;

        // A member does not only answer. They ask — what does comfort tier
        // mean, why do you want the registration number, how many rooms should
        // I put. Every one of those used to come back as "that did not answer
        // it", which is both untrue and the plainest sign that nothing was
        // listening. So the model answers, and the question they were on is
        // put to them again after.
        $answer = trim((string) ($data['answer'] ?? ''));
        $answer = mb_strlen($answer) > 400 ? '' : $answer;

        $checked = $this->keepValid($form, $known, (array) ($data['fields'] ?? []), $asked, array_keys($extras));

        // What was heard and could not be used. The app said only "I could not
        // use that one" and left the member guessing at what would have done
        // instead — which, when the box takes one of HCT's own values, is a
        // list we are holding and they are not. So it is read out, along with
        // the two ways past it.
        // A wrong answer to a box that takes one of HCT's own values arrives
        // two ways: as a value that is not on the list, or as nothing at all
        // because the model would not guess. Both leave the member none the
        // wiser about what would have done instead, and both are met the same
        // way — by reading the list out.
        $refused = null;
        // A member who declines has answered, and the list is the wrong thing to
        // read them: asked "क्या यह छोड़ सकता हूँ?" they were told "यह इनमें से
        // नहीं है — Trek, Cultural Immersion, ..." and, in the same breath,
        // "ठीक है, खाली छोड़ देते हैं". Two opposite replies to one sentence.
        // Nothing was written because they asked for nothing to be written.
        $missed = ! isset($data['revisit']) && ! isset($data['answer'])
            && ($data['declined'] ?? false) !== true
            && (in_array($asked, $checked['rejected'], true)
                || ($checked['fields'] === [] && $checked['rejected'] === []));

        if ($missed && ! str_contains($asked, '.')) {
            $choices = $this->choicesFor($form, $asked, $known, $language);
            $label = $this->labelFor($form, $asked, $known);

            $refused = $choices === null ? null : ($language === 'hi'
                ? 'यह इनमें से नहीं है: ' . implode(', ', $choices) . '। इनमें से कोई बताइए, या कहिए कि छोड़ दें।'
                : 'That did not match: it is one of these: ' . implode(', ', $choices) . '. Pick one, or say to leave it.');
        }

        // One column of a table becomes the row it belongs to, and a member
        // saying there are no more finishes the table for good.
        $rows = $this->assembleRows($form, $known, $checked['fields'], $asked);
        $checked['fields'] = $rows['fields'];
        $finished = $rows['finished'] === null ? [] : [$rows['finished']];

        // "There is no note", "koi add-on nahi hai". A member declining is an
        // answer, and until now it looked exactly like not being understood:
        // nothing was written, the same question came round, and a host with
        // nothing to add was asked three times and then had to find the Skip
        // button. It is only honoured where the field may be passed over —
        // the one that decides the shape of the form cannot be declined away.
        $declined = ($data['declined'] ?? false) === true
            && ! isset($data['revisit'])
            && $checked['fields'] === []
            && $this->skippable($form, $asked, $known);

        // The first answer is the one that brings the rest of the form into
        // existence: until a member says they have a homestay there are no
        // rooms and no nightly rate to fill, so "mera paanch kamre ka homestay
        // hai, pandrah sau rupaye" had two thirds of it fall on the floor.
        //
        // So the same sentence is read once more against the form it has just
        // brought into being. Once per listing, at the turn that decides its
        // shape, and only when there is now something to find.
        if (($spec['skippable'] ?? true) === false && isset($checked['fields'][$asked])) {
            $checked['fields'] += $this->mineAgain(
                $form,
                $known + [$asked => $checked['fields'][$asked]],
                $asked,
                $said,
                $skipped,
                $language,
                $this->labelFor($form, $asked, $known) . ': ' . $checked['fields'][$asked],
            );
        }

        // Every box this turn filled, named with what went into it.
        //
        // A member who says one sentence and has three boxes filled from it has
        // no way of knowing which three, or what went where, and the one thing
        // that can go wrong here is two numbers changing places. Being told is
        // what makes it safe to do at all: they hear it, and they can say the
        // price is wrong and go back to it.
        //
        // It used to name only the boxes filled WITHOUT being asked, on the
        // reasoning that the answered one was obvious. It was not: the app put
        // a small "filled 1 box" under the member's own line, in English, under
        // a Hindi conversation, saying how many and never which. That is gone,
        // and this says it instead — in their tongue, in the assistant's own
        // reply, where they are already reading.
        $left = [];

        if ($checked['fields'] !== []) {
            // Against the form as it now is, not as it was when the turn
            // began: on the turn that settles what a listing is, none of these
            // boxes existed a moment ago and every heading came back empty.
            $nowKnown = $checked['fields'] + $known;

            // A sentence, not a label and a colon. "Written down, Service
            // type: A vehicle." reads like a receipt; the member wants to hear
            // that they were understood, which is a thing one person says to
            // another.
            $named = [];
            foreach ($checked['fields'] as $key => $value) {
                $box = $this->labelFor($form, $key, $nowKnown);
                $said = $this->spokenValue($form, $key, $value, $language, $nowKnown);
                $named[] = $language === 'hi' ? "{$box} में {$said}" : "{$said} into {$box}";
            }

            $join = $language === 'hi' ? ' और ' : ' and ';
            $all = count($named) > 2
                ? implode(', ', array_slice($named, 0, -1)) . $join . end($named)
                : implode($join, $named);

            $left[] = $language === 'hi'
                ? "मैंने {$all} लिख दिया है।"
                : "I have written {$all}.";
        }

        if ($declined) {
            // A table is declined by name; anything else by its own.
            $finished[] = explode('.', $asked, 2)[0];

            // And it is said aloud. Declining and being misheard arrive here
            // looking the same, and the model decides which — so a wrongly
            // read answer would otherwise slip past in silence, leaving a box
            // empty that the member believes they filled. Naming it costs one
            // line and means they can go back to it.
            $label = $this->labelFor($form, $asked, $known);
            $left[] = $language === 'hi'
                ? "ठीक है, {$label} खाली छोड़ देते हैं। बाद में फ़ॉर्म में भर सकते हैं।"
                : "All right, {$label} is left empty. You can fill it in on the form later.";
        }

        // "The name is wrong", "let me change the price". A member who has
        // moved past a box has, until now, had no way back to it by talking:
        // the assistant only ever walks forward, and the only way to correct
        // anything was to close the sheet and type over it.
        //
        // Reopening is blanking. The box is emptied, which is what puts it
        // back in front of the next question, and the app is told so it can
        // stop counting it among the ones passed over.
        $reopened = $this->fieldNamed($form, $known, trim((string) ($data['revisit'] ?? '')));
        if ($reopened !== null) {
            $checked['fields'][$reopened] = '';
        }

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

        // The turn gave nothing back: no value, nothing turned away, no row
        // closed, and not a word to say. That is where a member who asked a
        // question ends up, and telling them they were not understood is the
        // one reply an assistant with an answer would never give. So it is
        // asked for one.
        //
        // Only here. A member who answers, declines, closes a table or is told
        // their answer was not on the list never reaches this line, and never
        // pays for the call.
        // Not conditioned on $answer being empty. The reading model has an
        // "answer" key of its own, written before there was a call whose only
        // job was answering — and asked to answer, it copies the member's
        // question back instead: "मुझे समझ नहीं आया, इसमें क्या भरूँ?" came
        // back as the answer to itself. Its job is reading a sentence for what
        // is in it, and answering is a second job it does badly. So a question
        // it spotted is handed to helpWith(), and its own answer is kept only
        // for when that comes back with nothing.
        // Nor on $say being empty. "समझ में नहीं आया।" is what the reading
        // model offers when it could not use the sentence — which is the exact
        // turn a member needs an answer, not to be told they were not
        // understood. That line was blocking the one call that could help.
        $help = ($finished === []
                && $checked['fields'] === [] && $checked['rejected'] === [])
            ? $this->helpWith($form, $asked, $said, $language, $known)
            : null;

        // And where an answer was found, the shrug is dropped: a member should
        // not hear "I did not understand" and a good answer in the same breath.
        if ($help !== null) {
            $say = '';
        }

        return [
            'fields' => $checked['fields'],
            // Boxes reached on the way here that have to be done by hand, and
            // the words to say about each — preceded by anything worth reading
            // back out of what was just recorded.
            'guidance' => array_merge($echo, $left, $here['guidance'], $ahead['guidance']),
            // `$finished` is a table the member has just closed — "that is
            // all" — which the app must remember, or the next turn offers
            // another row and the offer never ends.
            'passed' => array_merge($here['passed'], $finished, $ahead['passed']),
            // Nothing was taken and nothing was turned away — the answer did
            // not answer the question. Left unsaid, the same question simply
            // comes round again and the member repeats themselves at a screen
            // that looks deaf. It is the one thing they were never told.
            //
            // Said when a member declines, or when they close a table, it is
            // worse than useless: they answered, the assistant moved on, and
            // then told them it had not understood.
            // An answer of its own comes first, then one asked for on the spot,
            // then the list read out when what they said was not on it. Only
            // when all three come to nothing is a member told they were not
            // understood — which is now the rarest thing said, not the usual.
            'note' => $help
                ?: ($answer
                    ?: ($refused
                        ?: ($finished === [] && $checked['fields'] === [] && $checked['rejected'] === []
                            ? ($say ?: $this->notHeard($form, $asked, $language, $known))
                            : null))),
            // The model's own wording of the next question, used only when the
            // question it was wording is the one that actually came next.
            // Otherwise the written question is put after whatever it said —
            // which is where this began, and is still perfectly serviceable.
            'reply' => $answer !== '' && $next !== null
                ? $this->phrase($this->questionFor($form, $next, $language, $filled), $language, (string) $this->labelFor($form, $next, $filled), $this->choicesFor($form, $next, $filled, $language), $this->meaningsFor($this->specFor($form, $next, $filled), $language), $next === $asked)
                : ($next === null
                ? ($say ?: null)
                : ($ask !== '' && $next === $guessed
                    ? trim($say . ' ' . $ask)
                    : trim($say . ' ' . $this->phrase($this->questionFor($form, $next, $language, $filled), $language, (string) $this->labelFor($form, $next, $filled), $this->choicesFor($form, $next, $filled, $language), $this->meaningsFor($this->specFor($form, $next, $filled), $language), $next === $asked)))),
            'asked' => $next,
            'label' => $next === null ? null : $this->labelFor($form, $next, $filled),
            'choices' => $next === null ? null : $this->choicesFor($form, $next, $filled, $language),
            'rejected' => $checked['rejected'],
            // A box the member asked to go back to, emptied and put in front
            // of them again. Named so the app can take it out of what it is
            // treating as passed over, or it would be stepped straight past.
            'reopened' => $reopened,
            'done' => $next === null,
            'unavailable' => false,
        ];
    }

    /**
     * Whichever AI the voice assistant is set to speak through.
     *
     * The portal's trek-planning chat is not asked and is not affected: it goes
     * on calling GroqService directly from AjaxController::callAi. The two are
     * different products that happen to share a wire format, and the rule here
     * is that neither reaches into the other.
     *
     * Groq's free tier stops dead at 200,000 tokens a day for the whole
     * account, which is four or five listings, and there is no paid tier on
     * offer to this account. OpenAI has no daily ceiling. Transcription stays
     * on Groq either way — its audio allowance is counted separately, has never
     * once run out, and costs a third of what OpenAI asks for the same minute.
     *
     * @return GroqService|OpenAiService
     */
    private function ai(): object
    {
        // Settings first, .env behind it. HCT switches this from the control
        // panel, the way everything else on this project is switched: prompts,
        // system lists, the greeting. .env still answers when the row is
        // missing, so a fresh checkout behaves as it always did.
        //
        // Anything but the two known names reads as Groq. The panel renders
        // every setting as a plain text box, so 'grok' or a trailing space is a
        // matter of time, and a typo must not take the assistant down.
        $chosen = strtolower(trim((string) \App\Models\Setting::getValue(
            'voice_provider',
            config('voice.provider', 'groq'),
        )));

        if ($chosen === 'openai') {
            $openai = app(OpenAiService::class);
            if ($openai->isAvailable()) {
                return $openai;
            }

            // Configured for OpenAI with no key set. Falling back is better
            // than falling silent, and the log says which so nobody spends an
            // afternoon wondering why the wording sounds like the old model.
            Log::warning('Voice is set to OpenAI but OPENAI_API_KEY is empty — using Groq');
        }

        return app(GroqService::class);
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
            ? ($label ? "यह समझ नहीं आया, कृपया {$label} के बारे में बताइए।" : 'यह समझ नहीं आया।')
            : ($label ? "That did not answer it, please tell me about {$label}." : 'I did not catch that.');
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
     * Answer a member who said something that was not an answer.
     *
     * The turn's own reading has one job — take the value out of the sentence —
     * and it is run cold so the values it takes can be trusted. Answering is a
     * second duty, and a model at temperature 0.10 with a field to fill drops
     * it about as often as it does it: the member asks what to put in the box
     * and is told their answer was not understood, which is both untrue and
     * the plainest possible sign that nothing is listening.
     *
     * So when a turn yields nothing at all — no value, nothing turned away,
     * no decline, no going back, no answer — the sentence is put to a model
     * whose only job is to reply to it. That is the case a member is in when
     * they are confused, and it is the one case where an answer matters more
     * than a value.
     *
     * One extra call, only on those turns. A member who is answering normally
     * never pays for it.
     */
    private function helpWith(
        string $form,
        string $field,
        string $said,
        string $language,
        array $known,
    ): ?string {
        $spec = $this->specFor($form, $field, $known);
        if ($spec === []) {
            return null;
        }

        $choices = $this->choicesFor($form, $field, $known, $language);

        $prompt = app(PromptBuilderService::class)->build('provider_voice_help', [
            'reply_in' => $language === 'hi' ? 'Hindi' : 'English',
            // The heading, not the key. Asked about the box by its key, the
            // model said the key back — "आपको 'service_type' में..." — which
            // names nothing a member has ever seen on their screen.
            'heading' => $spec['label'] ?? '(not named)',
            'about' => $spec['ask'] ?? '',
            'question' => $this->questionFor($form, $field, $language, $known) ?? '',
            // What may be answered, in the words the member will see beneath
            // the question, and what HCT says each one covers. Half of being
            // stuck is not knowing what the choices are.
            'choices' => $choices ? implode(', ', $choices) : '(free text: anything they like)',
            'meanings' => ($m = $this->meaningsFor($spec, $language)) === null ? '' : "\n\nWhat each covers:\n" . $m,
            'filled' => $this->filledLabels($form, $known) ?: '(nothing yet)',
            'said' => $said,
        ]);

        if (! $prompt) {
            return null;
        }

        $answer = $this->ai()->chat([
            ['role' => 'system', 'content' => $prompt['system_prompt']],
            ['role' => 'user',   'content' => $prompt['user_prompt']],
        ], [
            'groq_model' => $prompt['model'] ?: null,
            // Ignored by OpenAI, which takes its model from config: the four
            // prompt rows name a Groq model by its Groq name.
            'openai_model' => config('openai.model'),
            'temperature' => $prompt['temperature'],
            'max_tokens' => $prompt['max_tokens'],
            'format' => 'json',
            'reasoning_effort' => 'low',
        ]);

        $said = trim((string) (json_decode((string) ($answer['content'] ?? ''), true)['answer'] ?? ''));

        // Long enough to have started lecturing is long enough to have lost
        // them: the question is put again straight after this, and a member
        // answers what they heard last.
        return ($said === '' || mb_strlen($said) > 400) ? null : $said;
    }

    /**
     * Read the same sentence again, against the boxes the answer just created.
     *
     * Called only where the answer settles what the form is — the kind of
     * service, the kind of experience — because those are the turns where the
     * boxes a member has already spoken about did not exist yet to be offered.
     *
     * Everything it finds goes through the same gate as anything else, and it
     * is announced aloud with the rest.
     *
     * @return array<string,mixed>
     */
    private function mineAgain(
        string $form,
        array $known,
        string $asked,
        string $said,
        array $skipped,
        string $language,
        string $taken = '',
    ): array {
        // A box that holds a name is left out of this. It is asked next in any
        // case, and a sentence that says what KIND of thing somebody has is
        // exactly where a name gets invented from: "mera homestay hai" was
        // read as a place called Homestay. Nothing is lost by waiting one
        // question, and a listing named after its own category is worse than
        // one named a moment later.
        //
        // The boxes that grade rather than record are already kept out by
        // extrasFor, for the same reason read the other way round: "होमस्टे
        // चलाता हूँ" put Cat D - Basic/Homestay into the comfort tier, which is
        // a guess about how good somebody's rooms are made from one word.
        $extras = array_filter(
            $this->extrasFor($form, $known, $asked, $skipped),
            fn ($field) => ! str_contains(mb_strtolower((string) ($field['label'] ?? '')), 'name'),
        );

        if ($extras === []) {
            return [];
        }

        $prompt = app(PromptBuilderService::class)->build('provider_voice_mine', [
            'reply_in' => $language === 'hi' ? 'Hindi' : 'English',
            'boxes' => $this->extrasLines($extras, $known, $language),
            // What the first reading already took out of this sentence. Told
            // only to find what it could, it took the word that had just
            // answered the question — "mera homestay hai" gave the kind of
            // place, and then gave "homestay" again as the name of it.
            'taken' => $taken ?: '(nothing yet)',
            'said' => $said,
        ]);

        if (! $prompt) {
            return [];
        }

        $answer = $this->ai()->chat([
            ['role' => 'system', 'content' => $prompt['system_prompt']],
            ['role' => 'user',   'content' => $prompt['user_prompt']],
        ], [
            'groq_model' => $prompt['model'] ?: null,
            // Ignored by OpenAI, which takes its model from config: the four
            // prompt rows name a Groq model by its Groq name.
            'openai_model' => config('openai.model'),
            'temperature' => $prompt['temperature'],
            'max_tokens' => $prompt['max_tokens'],
            'format' => 'json',
            'reasoning_effort' => 'low',
        ]);

        $data = json_decode((string) ($answer['content'] ?? ''), true);

        return is_array($data)
            ? $this->keepValid($form, $known, (array) ($data['fields'] ?? []), null, array_keys($extras))['fields']
            : [];
    }

    /**
     * Boxes further down that a member may have answered without being asked.
     *
     * "Mera paanch kamre ka homestay hai, pandrah sau rupaye" answers three
     * things at once, and being asked the other two afterwards is exactly what
     * makes this feel like a form. So the model is told which boxes are coming
     * and may fill any it heard plainly.
     *
     * Every kind of box, not only the ones holding a number or free text.
     * Boxes taking one of HCT's own values, and boxes answering whether
     * something is so, used to be left out: a list has to be sent with the box
     * to be answered safely, and four lists a turn was a third of the Groq
     * minute. The cost of leaving them out was that a member answering one of
     * them a question early was simply not heard. "Haan main khud chalata
     * hoon" and "yes I speak some English" both went nowhere, in both tongues,
     * and the same question came round again as though nothing had been said.
     * The lists turn out to be three to eleven short names; sent by name alone,
     * without the note under each, they cost a twentieth of what the fear was.
     *
     * A table and a box the form says must be typed are still left out. Neither
     * can be answered in passing: one has a conversation of its own, and the
     * other cannot be spoken into at all.
     *
     * Ten at most, nearest the conversation first, and never past the box that
     * decides the shape of the rest.
     *
     * @return array<string,array<string,mixed>>
     */
    /**
     * The boxes of a turn, written out for the model.
     *
     * One method rather than one at each call site: the two had drifted a word
     * apart already, and a box described differently in the two passes is a box
     * answered differently by them.
     *
     * A box holding one of HCT's own values carries that list. Without it the
     * model writes something perfectly reasonable that is not on the list, the
     * gate drops it, and the member is asked again with no idea that they had
     * already answered.
     */
    private function extrasLines(array $extras, array $known = [], string $language = 'en'): string
    {
        return implode("\n", array_map(function ($key, $field) use ($known, $language) {
            $shape = match ($field['type'] ?? 'string') {
                'int' => 'a whole number, digits only',
                'number' => 'a number, digits only',
                'bool' => 'true or false',
                'multi' => 'a JSON array of one or more',
                'months' => 'the month numbers, 1 to 12',
                default => 'text, in English',
            };

            if ($allowed = $this->allowedFor($field)) {
                // A box whose values are codes rather than words needs the
                // words beside them, in the tongue being spoken. Sent as bare
                // codes, "थोड़ा मुश्किल" was filed as `challenging` — the grade
                // above it, and the word for it in this very schema. The box
                // being ASKED about gets this through meaningsFor; a box
                // answered in passing was getting nothing, and how hard a trek
                // is nearly always gets answered in passing.
                //
                // The words only, never HCT's notes on them: those are
                // paragraphs, and ten paragraphs a turn is the whole allowance.
                $words = $field['words'][$language] ?? null;

                $shape .= ', copied exactly from: ' . implode(', ', $words
                    ? array_map(
                        fn ($value, $i) => $value . (isset($words[$i]) ? ' (' . $words[$i] . ')' : ''),
                        $allowed,
                        array_keys($allowed),
                    )
                    : $allowed);
            }

            // A box that gathers, with something already in it. Say what it
            // holds and ask for the whole of it back, so the member's second
            // thought joins the first instead of replacing it or falling out.
            $held = $known[$key] ?? null;
            if (($field['accrues'] ?? false) === true && is_string($held) && trim($held) !== '') {
                $shape .= sprintf(
                    '; this box already holds "%s", so where they have told you something further for it, give the whole box back with the new part joined on, and leave the box out where they have not',
                    $held,
                );
            }

            return sprintf('%s: %s (%s)', $key, $field['ask'] ?? '', $shape);
        }, array_keys($extras), $extras));
    }

    private function extrasFor(string $form, array $known, string $asked, array $skipped): array
    {
        if (str_contains($asked, '.')) {
            return [];
        }

        $schema = $this->schema($form, $known);
        $at = array_search($asked, array_keys($schema), true);

        if ($at === false) {
            return [];
        }

        // Can this box be answered in passing at all?
        $open = function (string $key, array $field) use ($known, $skipped): bool {
            if (in_array($key, $skipped, true)) {
                return false;
            }

            // A table has a conversation of its own, and a box the form says
            // must be typed cannot be spoken into.
            if (isset($field['row']) || isset($field['manual'])) {
                return false;
            }

            // A box that GRADES rather than records is never filled from a
            // sentence about something else. Asked what their place is called,
            // a member said "पहाड़ी होमस्टे" and it went into the accommodation
            // grade as Cat D: the name was lost, and the grade was invented
            // out of the word homestay. See the flag on those fields.
            if (($field['asked_only'] ?? false) === true) {
                return false;
            }

            $value = $known[$key] ?? null;

            // A box with something in it is done with, except the ones that
            // gather. A member describing their work rarely says all of it at
            // once: "मैं गाइड हूँ, ट्रैक पर लोगों को ले जाता हूँ" put trekking
            // into Specialties, and when they went on to say they show people
            // birds and the forest that had nowhere to go. The box was full, so
            // it was never offered, and a full box is never asked about again
            // either. The second half of what they told us was simply lost.
            return $value === null || $value === '' || $value === []
                || ($field['accrues'] ?? false) === true;
        };

        $keys = array_keys($schema);
        $ahead = [];
        $behind = [];

        // What is coming. Stops at anything that changes the shape of the form,
        // because the boxes past it may not exist once it is answered.
        for ($i = $at + 1; $i < count($keys); $i++) {
            if (($schema[$keys[$i]]['skippable'] ?? true) === false) {
                break;
            }
            if ($open($keys[$i], $schema[$keys[$i]])) {
                $ahead[] = $keys[$i];
            }
        }

        // And what is behind, nearest first. A box back there that is still
        // empty was stepped over rather than answered, and a member may well
        // be answering it now: asked what makes the trek unique, one began
        // describing the days, which belongs in the box just above. Looking
        // only forwards, that went nowhere.
        for ($i = $at - 1; $i >= 0; $i--) {
            if ($open($keys[$i], $schema[$keys[$i]])) {
                $behind[] = $keys[$i];
            }
        }

        // The numbers a member volunteers before anyone asks. A homestay owner
        // says "चार कमरे हैं और आठ लोग रुक सकते हैं" in their first breath,
        // and on the stay form those two boxes sit twenty questions down,
        // far past anything adjacency would reach.
        $always = array_keys(array_filter(
            $schema,
            fn ($field, $key) => ($field['listen_always'] ?? false) === true && $open($key, $field),
            ARRAY_FILTER_USE_BOTH,
        ));

        // Nearest first, and forward before back at the same distance. Taking
        // all of what is ahead before any of what is behind spent the whole
        // allowance on boxes eight questions away and dropped the one directly
        // above, which is the one a member is most likely to still be talking
        // about.
        $near = [];
        for ($step = 1; $step < count($keys); $step++) {
            if (isset($ahead[$step - 1])) {
                $near[] = $ahead[$step - 1];
            }
            if (isset($behind[$step - 1])) {
                $near[] = $behind[$step - 1];
            }
        }

        $extras = [];
        foreach ([...$always, ...$near] as $key) {
            $extras[$key] = $schema[$key];
            if (count($extras) === self::EXTRAS_MAX) {
                break;
            }
        }

        return $extras;
    }

    /**
     * One question, said differently.
     *
     * The questions are written down, which is what keeps them accurate and in
     * order — and is also why the same forty sentences went to every member of
     * the collective in the same words. The model asking is not choosing what
     * to ask; it is choosing how, and if it fails or wanders the written one is
     * used, so the worst case is what this has always done.
     *
     * A tenth the size of a turn and asked far more often, so it has a prompt
     * of its own rather than the assistant's.
     */
    public function phrase(
        ?string $question,
        string $language,
        string $label = '',
        ?array $choices = null,
        ?string $notes = null,
        bool $again = false,
    ): ?string
    {
        if ($question === null || trim($question) === '') {
            return $question;
        }

        // The same box, put a second time. The example that goes with a list of
        // filing codes is worth hearing once; a member who did not answer heard
        // "an authentic village homestay with a shared bathroom would be Cat D"
        // three turns running, because each call is written fresh and has no
        // memory of the last. The prompt asks for a different example every
        // time, which is not something it can do. So on a second telling there
        // is no example, and the question stands on its own.
        if ($again && $notes !== null) {
            $notes .= "\n\nThey have already heard this question once, and the "
                . 'example that went with it. Give no example this time: say the '
                . 'question and nothing after it.';
        }

        // The ways in. Told only to say the same thing differently, the model
        // said the same thing: "What does it cost?" came back word for word
        // four times out of four, because there is no obviously different way
        // to put four words and nothing pushed it to look for one. So it is
        // handed a construction instead, and none of these can be satisfied by
        // repeating the written sentence.
        //
        // Seven rather than four. With four, one drawn twice inside a few asks
        // was common enough that a member heard the same sentence again while
        // still on the same box.
        $angles = [
            'Ask it as a follow-on to what they just said: "And what about ...?", "और ...?"',
            'Invite it rather than ask it: "ज़रा बताइए ...", "Tell me ...".',
            // "begin with आप" on its own turned the member into the thing:
            // "यह किस तरह की जगह है?" came back as "आप किस प्रकार की जगह हैं?" —
            // what kind of place are you. And an English example of "at your
            // place" produced "What do you call the place at your place?". So
            // it is put as plain possession now.
            'Put it as theirs: "आपकी जगह ...", "आपके यहाँ ...", "your place ...", "yours".',
            'Ask it the way somebody who has been talking to them for a few minutes would: easy, not formal.',
            'Lead with what you are doing with it: "मैं लिख लेता हूँ, ...", "Let me note this down: ...".',
            'Soften it: "अगर बता दें तो ...", "If you could tell me ...".',
            'Put it as a gentle check: "तो ...?", "So ...?".',
        ];

        // Which way to come at it is now drawn fresh every time it is asked.
        // It used to be count($filled) — how many boxes were already done — so
        // two members at the same point in the same form heard the identical
        // sentence, and a box asked a second time was asked in the very same
        // words as the first.
        $wording = function (int $angle) use ($angles, $question, $language, $label, $choices, $notes): ?string {
            $prompt = app(PromptBuilderService::class)->build('provider_voice_ask', [
                'reply_in' => $language === 'hi' ? 'Hindi' : 'English',
                // Which way to come at it this time. Told only to say the same
                // thing differently, it said the same thing: "What does it cost?"
                // came back word for word four times out of four, because there is
                // no obviously different way to put four words and nothing was
                // pushing it to look for one.
                // None of the four may be answered by handing the written
                // sentence back, which is what "ask it plainly" turned out to
                // mean: each asks for a different construction.
                'angle' => $angles[$angle],
                // What the form calls this box, so it can be named.
                'label' => $label ?: '(not named)',
                // What may be answered, and what HCT says each one covers.
                //
                // "Cat D - Basic/Homestay" is how the collective files a room and
                // means nothing to the person who sleeps in it. The notes beside
                // those values are written in plain words and were only ever shown
                // to the model reading the answer; a member hearing the question
                // got the filing code and no help at all.
                'choices' => $choices ? implode(', ', $choices) : '(it is not a list: anything can be said)',
                'notes' => $notes ?: '(none)',
                'question' => $question,
            ]);

            if (! $prompt) {
                return null;
            }

            $answer = $this->ai()->chat([
                ['role' => 'system', 'content' => $prompt['system_prompt']],
                ['role' => 'user',   'content' => $prompt['user_prompt']],
            ], [
                'groq_model' => $prompt['model'] ?: null,
            // Ignored by OpenAI, which takes its model from config: the four
            // prompt rows name a Groq model by its Groq name.
            'openai_model' => config('openai.model'),
                'temperature' => $prompt['temperature'],
                'max_tokens' => $prompt['max_tokens'],
                'format' => 'json',
                'reasoning_effort' => 'low',
            ]);

            $said = trim((string) (json_decode((string) ($answer['content'] ?? ''), true)['ask'] ?? ''));

            // Longer than the question it came from by any margin means it has
            // started explaining, and a member answers what they heard last.
            // '' means the model answered but the answer is no good — empty,
            // or long enough that it has started explaining and a member would
            // answer the explanation. That is worth another go. null means it
            // did not answer at all, and another go would be the same silence.
            return ($said === '' || mb_strlen($said) > mb_strlen($question) + 160)
                ? ''
                : $said;
        };

        $angle = random_int(0, count($angles) - 1);
        $said = $wording($angle);

        // Handing the written sentence back is the one wrong answer, and it
        // still comes sometimes. One more go, at a different angle. It costs a
        // small call, and only on the turns that would otherwise sound exactly
        // like the last one.
        if ($said === '' || $said === $question) {
            $said = $wording(($angle + 1) % count($angles));
        }

        return $said ?: $question;
    }

    /**
     * A stored value said back the way the member would recognise it.
     *
     * The column keeps a code — `accommodation`, `less_than_day`, a bare true —
     * and reading that back to somebody who said "मेरा एक होमस्टे है" tells them
     * nothing about whether it was understood. Where words were written beside
     * the codes, those are used; where there are none, the value stands, since
     * a list value is already the words HCT chose.
     */
    private function spokenValue(string $form, string $field, mixed $value, string $language, array $known = []): string
    {
        $tongue = $language === 'hi' ? 'hi' : 'en';

        if (is_bool($value)) {
            return $value
                ? ($tongue === 'hi' ? 'हाँ' : 'yes')
                : ($tongue === 'hi' ? 'नहीं' : 'no');
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($one) => $this->spokenValue($form, $field, $one, $language, $known),
                $value,
            ));
        }

        $spec = $this->specFor($form, $field, $known);

        // What the FORM shows, not how the question was put. The question asks
        // conversationally — "a place to stay", "गाड़ी" — and that is right for
        // asking. But a member who says "I have a homestay" and is told "I have
        // written A place to stay into Service type" then looks at the form and
        // reads "Accommodation", and has to work out for themselves that the
        // two are the same thing. The confirmation exists so they can check the
        // box, so it says what the box says.
        if (isset($spec['only']) && in_array($value, $spec['only'], true)) {
            return ucfirst(str_replace('_', ' ', (string) $value));
        }

        // A region is stored by id and no member has ever seen one.
        if (($spec['source'] ?? null) === 'regions') {
            $name = $this->regions()->firstWhere('id', (int) $value)?->name;
            if ($name) {
                return $name;
            }
        }

        return (string) $value;
    }

    /**
     * The choices to show beneath a question, or null when there are none.
     *
     * Three kinds of box have answers to show, and until now only the first
     * showed any:
     *
     *  - fed by one of HCT's lists, where the value shown is the value stored;
     *  - one of a fixed few, where the value stored is a code — `less_than_day`,
     *    `easy` — and what is shown is the words beside it. Those codes were
     *    left unshown rather than translated, so the first question of the rate
     *    card, which is one of these, offered nothing at all;
     *  - yes or no, which is two answers like any other and was being left to
     *    the member to guess at.
     */
    public function choicesFor(string $form, string $field, array $known = [], ?string $language = null): ?array
    {
        $spec = $this->specFor($form, $field, $known);
        $tongue = $language === 'hi' ? 'hi' : 'en';

        // HCT's own lists, and the valleys HECO works in — twenty of them, and
        // no member can be expected to guess which twenty.
        if (isset($spec['list']) || isset($spec['source'])) {
            return $this->allowedFor($spec) ?: null;
        }

        if (($spec['type'] ?? '') === 'bool') {
            return $tongue === 'hi' ? ['हाँ', 'नहीं'] : ['Yes', 'No'];
        }

        // No words written beside the codes means nothing fit to show, and a
        // raw code is worse than an empty space.
        return isset($spec['only']) && isset($spec['words'][$tongue])
            ? array_values($spec['words'][$tongue])
            : null;
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
     * The headings of the boxes already answered, for the model to name one of
     * when a member asks to go back.
     *
     * Headings rather than field names: it is what the member sees, what they
     * will say, and what fieldNamed() matches against on the way back.
     */
    private function filledLabels(string $form, array $known): string
    {
        $labels = [];
        foreach ($this->schema($form, $known) as $key => $field) {
            $value = $known[$key] ?? null;
            if ($value === null || $value === '' || $value === [] || ! isset($field['label'])) {
                continue;
            }
            // With the value beside it, not just the heading. Asked "what did
            // I say my place was called", the model could only repeat the
            // question back — it had been told which boxes were full and
            // nothing about what was in them.
            $shown = is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'yes' : 'no') : $value);
            $labels[] = $field['label'] . ': ' . $shown;
        }

        return implode(', ', $labels);
    }

    /**
     * The box a member has asked to go back to, or null if they named nothing
     * on this form.
     *
     * They say what it is about — "the name", "daam", "guide type" — and the
     * model hands that on as the nearest heading it knows. It is matched
     * against the headings the form actually shows, and only when it names one
     * of them is anything reopened: a wrong guess here would empty a box they
     * had already filled correctly.
     *
     * A box that was never filled is not reopened either. There is nothing to
     * go back to, and the conversation is already on its way there.
     */
    private function fieldNamed(string $form, array $known, string $said): ?string
    {
        $said = mb_strtolower(trim($said));
        if ($said === '') {
            return null;
        }

        foreach ($this->schema($form, $known) as $key => $field) {
            $label = mb_strtolower((string) ($field['label'] ?? ''));
            if ($label === '' || ! isset($known[$key]) || $known[$key] === '' || $known[$key] === []) {
                continue;
            }

            if ($said === $label || $said === mb_strtolower($key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Which question will probably follow, worked out by supposing the one
     * being asked is about to be answered.
     *
     * Wrong exactly when the answer decides what comes after it, which is why
     * the caller checks it against what actually came next before using the
     * wording built on it.
     */
    private function likelyNext(string $form, array $known, string $asked, array $skipped): ?string
    {
        // A column of a table cannot be stood in for this way — the row it
        // belongs to is half built and the schema does not describe it.
        if (str_contains($asked, '.')) {
            return null;
        }

        $walk = $this->walk($form, $known + [$asked => '—'], $skipped, 'en');

        return $walk['next'];
    }

    /**
     * How the assistant should sound on this turn, rotated so it does not
     * sound the same way twice running.
     *
     * The silent one earns its place: a person filling in a form does not
     * murmur after every answer, and a number following a number needs no
     * remark at all. Hearing nothing is what makes the others land.
     */
    private function toneFor(int $answered): string
    {
        return [
            'Say the thing back to them, briefly.',
            'Just acknowledge it: a word or two, nothing more.',
            'Return "say" as an empty string this time. The question stands on its own.',
            'Remark on what they said, the way somebody listening would.',
        ][$answered % 4];
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
    public function meaningsFor(array $field, ?string $language = null): ?string
    {
        // A fixed set the database enum already fixes, whose values are codes
        // rather than words: easy, moderate, challenging, extreme. The member
        // never says a code, and for these there is no HCT note to lean on —
        // so the words beside them, which the app already shows on screen, are
        // what the model gets. Without them a Hindi member saying "थोड़ा
        // मुश्किल" was filed as challenging, which is the next grade up and is
        // the word for it in this very schema. English was right, so nothing
        // showed until both tongues were walked side by side.
        //
        // Both tongues go, not only theirs: a member says "Cat A" and "easy"
        // in the middle of a Hindi sentence all day long.
        if (! isset($field['list'])) {
            if (! isset($field['only'], $field['words'])) {
                return null;
            }

            $theirs = $field['words'][$language ?? 'en'] ?? $field['words']['en'] ?? [];
            $english = $field['words']['en'] ?? [];

            return implode("\n", array_map(
                fn ($value, $i) => sprintf(
                    '"%s": %s',
                    $value,
                    ($theirs[$i] ?? $value) . (($english[$i] ?? null) && ($english[$i] !== ($theirs[$i] ?? null))
                        ? ' (' . $english[$i] . ')'
                        : ''),
                ),
                $field['only'],
                array_keys($field['only']),
            ));
        }

        $rows = SystemList::ofType($field['list'])
            ->get(['name', 'description'])
            ->reject(fn ($row) => in_array($row->name, $field['except'] ?? [], true))
            ->values();

        if ($rows->isEmpty() || $rows->every(fn ($row) => trim((string) $row->description) === '')) {
            return null;
        }

        return $rows
            ->map(fn ($row) => trim((string) $row->description) !== ''
                ? sprintf('"%s": %s', $row->name, trim($row->description))
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

    /**
     * The values a field will accept, or null when it takes free text.
     *
     * `except` drops values from a list that cannot apply on this side of it.
     * HCT's guide list is written for a traveller choosing what they want, so
     * it opens with "No Guide" — which a guide filling in their own rate card
     * can never be. Left in, it was read out to them: "do you provide no guide,
     * a local guide, or a certified one?"
     */
    public function allowedFor(array $field): ?array
    {
        if (isset($field['only'])) {
            return $field['only'];
        }
        if (isset($field['list'])) {
            return SystemList::ofType($field['list'])
                ->pluck('name')
                ->reject(fn ($name) => in_array($name, $field['except'] ?? [], true))
                ->values()
                ->all();
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
    public function keepValid(string $form, array $known, array $offered, ?string $asked = null, array $alsoAllowed = []): array
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
            // Named boxes only. With no field asked about and no list of
            // allowed ones this lets everything through, which is right for a
            // caller that has neither and wrong for one that has the second —
            // the pass that reads a sentence again is confined to the boxes it
            // was told to look for.
            if (($asked !== null || $alsoAllowed !== [])
                && $key !== $asked
                && ! in_array($key, $alsoAllowed, true)) {
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

            // Writing back what is already there is not writing anything. It
            // matters because every field written is read out — "मैंने
            // Specialties में trekking लिख दिया है" — and a box that gathers is
            // offered again every turn, so an unchanged answer would be
            // announced over and over as though it were news.
            if (array_key_exists($key, $known) && $known[$key] === $cast) {
                continue;
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
