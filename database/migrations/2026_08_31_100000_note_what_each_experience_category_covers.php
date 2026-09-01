<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;

/**
 * The three experience categories had names and nothing else.
 *
 * A member never says "Workshops, Handicrafts, Local Knowledge & Storytelling".
 * They say "I teach cooking to tourists", and the voice assistant — given the
 * three names and no notion of what belongs under each — filed that under
 * guiding. The same silence is why "I am a guide" recorded nothing at all.
 *
 * `system_lists.description` has always been there and HCT can edit it from
 * the control panel, so what each category covers belongs in it rather than in
 * the prompt, where only a deploy could change it.
 *
 * Only rows still without a note are touched: a description HCT has since
 * written by hand is theirs, not ours to overwrite.
 */
return new class extends Migration {
    private const COVERS = [
        'Experiential accommodation' =>
            "Staying at the member's own place — a homestay, a village house, a farm stay, a lodge. The traveller sleeps there.",
        'Guided Cultural & Outdoor Activities' =>
            'Taking travellers out and showing them something — treks, village and nature walks, wildlife and bird watching, sightseeing, guided visits.',
        'Workshops, Handicrafts, Local Knowledge & Storytelling' =>
            'Teaching or demonstrating a skill or a tradition — cooking classes, weaving, pottery, woodwork, farming, music, folklore and storytelling.',
    ];

    public function up(): void
    {
        foreach (self::COVERS as $name => $covers) {
            SystemList::where('list_type', 'experience_category')
                ->where('name', $name)
                ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $covers]);
        }
    }

    public function down(): void
    {
        // Only what this migration wrote. A note HCT has reworded since is not
        // this migration's to take away.
        foreach (self::COVERS as $name => $covers) {
            SystemList::where('list_type', 'experience_category')
                ->where('name', $name)
                ->where('description', $covers)
                ->update(['description' => null]);
        }
    }
};
