<?php

use App\Models\SystemList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signup fields the client-approved design asks for that the table had no home
 * for: the business identity block (type / registration / year) and a proper
 * postal address (street + city + postal code + country) rather than one free
 * text blob.
 */
return new class extends Migration {
    /** Business types offered in the design's step 3 select. */
    private const BUSINESS_TYPES = [
        'Sole proprietor',
        'Registered company',
        'Partnership',
        'Cooperative',
        'Informal / individual',
    ];

    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('provider_type');
            $table->string('registration_number')->nullable()->after('business_type');
            $table->unsignedSmallInteger('year_established')->nullable()->after('registration_number');
            // `address` stays as the street line; these complete the postal address.
            $table->string('city')->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->string('country')->nullable()->after('postal_code');
        });

        // Options are DB-managed like every other dropdown, so HCT can edit them.
        foreach (self::BUSINESS_TYPES as $i => $name) {
            SystemList::firstOrCreate(
                ['list_type' => 'business_type', 'name' => $name],
                ['is_active' => true, 'sort_order' => $i + 1],
            );
        }
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn([
                'business_type', 'registration_number', 'year_established',
                'city', 'postal_code', 'country',
            ]);
        });

        SystemList::where('list_type', 'business_type')->delete();
    }
};
