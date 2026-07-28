<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SystemList;

/**
 * The fields the client's data-collection document asks each kind of OSP for.
 *
 * The existing rate row already carried the shared parts — description, price,
 * unit, notes — so only what a specific service needs is added here. A taxi is
 * the clearest case: a single price could never hold two per-kilometre rates,
 * and the plains and the hills are priced differently for a real reason.
 */
return new class extends Migration
{
    /** Languages a guide can be asked for "from a list", per the document. */
    private const LANGUAGES = [
        'English', 'Hindi', 'French', 'German', 'Spanish', 'Italian',
        'Russian', 'Japanese', 'Mandarin', 'Hebrew', 'Nepali',
    ];

    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            // Taxi. Two per-km rates, because a hill kilometre costs more to
            // drive than a plains one.
            $table->boolean('ac_available')->nullable()->after('vehicle_type');
            $table->unsignedSmallInteger('vehicle_count')->nullable()->after('ac_available');
            $table->decimal('price_per_km_plains', 10, 2)->nullable()->after('vehicle_count');
            $table->decimal('price_per_km_hills', 10, 2)->nullable()->after('price_per_km_plains');
            $table->decimal('ac_extra_cost', 10, 2)->nullable()->after('price_per_km_hills');

            // Guide. `price` stays the one-day wage; a multi-day booking with a
            // night away is a different rate, not a multiple of it.
            $table->boolean('speaks_english')->nullable()->after('specialties');
            $table->json('languages')->nullable()->after('speaks_english');
            $table->decimal('wage_multi_day', 10, 2)->nullable()->after('languages');
            $table->boolean('is_certified')->nullable()->after('wage_multi_day');
            $table->boolean('has_first_aid')->nullable()->after('is_certified');

            // Rental. Charges per day are the row's own price; what is being
            // rented and what is held against it are not.
            $table->string('rental_item', 150)->nullable()->after('has_first_aid');
            $table->decimal('security_deposit', 10, 2)->nullable()->after('rental_item');

            // Standard accommodation. A hotel is a place before it is a rate,
            // so it needs to say where it is and how many it sleeps.
            $table->decimal('latitude', 10, 7)->nullable()->after('security_deposit');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('guest_capacity')->nullable()->after('longitude');
            $table->text('seasonality_notes')->nullable()->after('guest_capacity');
            $table->json('photos')->nullable()->after('seasonality_notes');
        });

        // "Rental" is a service the document asks for and the list never had.
        SystemList::firstOrCreate(
            ['list_type' => 'service_type', 'name' => 'Rental'],
            [
                'description' => 'Equipment or gear hired out by the day.',
                'is_active' => true,
                'sort_order' => 60,
            ]
        );

        // "Other langages (from a list)" — so it is a list, not free text, and
        // HCT can extend it from the control panel like every other one.
        foreach (self::LANGUAGES as $i => $language) {
            SystemList::firstOrCreate(
                ['list_type' => 'language', 'name' => $language],
                ['is_active' => true, 'sort_order' => ($i + 1) * 10]
            );
        }
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropColumn([
                'ac_available', 'vehicle_count',
                'price_per_km_plains', 'price_per_km_hills', 'ac_extra_cost',
                'speaks_english', 'languages', 'wage_multi_day',
                'is_certified', 'has_first_aid',
                'rental_item', 'security_deposit',
                'latitude', 'longitude', 'guest_capacity', 'seasonality_notes', 'photos',
            ]);
        });

        SystemList::where('list_type', 'language')->delete();
        SystemList::where('list_type', 'service_type')->where('name', 'Rental')->delete();
    }
};
