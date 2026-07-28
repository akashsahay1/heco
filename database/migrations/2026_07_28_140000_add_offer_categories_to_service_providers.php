<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member says they offer (signup screen 8).
 *
 * The client's rule: "Whatever has been selected in your screen 5 comes here
 * but with the different categories that can be selected. If the person has a
 * boutique homestay, they will select the Experiential accommodation and if the
 * same person has a taxi, they can also select taxi services."
 *
 * So the options are the same categories they will later list under — what you
 * declare at signup is exactly what you can go on to create. They are stored
 * per role rather than in one bag, because an HLH "Experiential accommodation"
 * (a remote homestay, a heritage house) and an OSP "Standard accommodation"
 * (a hotel room) are different products that happen to share a word.
 *
 * The older services_offered / accommodation_categories / vehicle_types /
 * guide_types / activity_types columns are a different axis and are left alone.
 */
return new class extends Migration {
    /** HLH — the three experience categories from the data-collection PDF. */
    private const EXPERIENCE_CATEGORIES = [
        'Experiential accommodation',
        'Guided Cultural & Outdoor Activities',
        'Workshops, Handicrafts, Local Knowledge & Storytelling',
    ];

    /** OSP — the five service categories from the same document. */
    private const SERVICE_CATEGORIES = [
        'Standard accommodation',
        'Taxi services',
        'Guide',
        'Rental',
        'Other services',
    ];

    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->json('experience_categories')->nullable()->after('provider_types');
            $table->json('service_categories')->nullable()->after('experience_categories');
            // "Other services (free text)" — the catch-all the client asked for
            // so an OSP is never turned away for doing something unlisted.
            $table->string('other_services')->nullable()->after('service_categories');
        });

        foreach (self::EXPERIENCE_CATEGORIES as $i => $name) {
            SystemList::firstOrCreate(
                ['list_type' => 'experience_category', 'name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1],
            );
        }

        foreach (self::SERVICE_CATEGORIES as $i => $name) {
            SystemList::firstOrCreate(
                ['list_type' => 'service_category', 'name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1],
            );
        }
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['experience_categories', 'service_categories', 'other_services']);
        });

        SystemList::whereIn('list_type', ['experience_category', 'service_category'])->delete();
    }
};
