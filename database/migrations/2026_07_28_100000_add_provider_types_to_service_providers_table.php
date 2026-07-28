<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A provider can be more than one thing at once.
 *
 * The client's signup screen 5 asks "What best defines you?" and allows several
 * answers — an HLH may also be an OSP, an HRP may also be an HLH and/or an OSP.
 * The single provider_type enum cannot express that, so the set lives in
 * provider_types. provider_type stays as the primary type: it still drives the
 * display badge, and it is what the enum-typed columns and older rows rely on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->json('provider_types')->nullable()->after('provider_type');
        });

        // Existing rows are exactly one type — seed the set from it so no gate
        // that reads provider_types sees an empty list for an old provider.
        DB::table('service_providers')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('service_providers')
                    ->where('id', $row->id)
                    ->update(['provider_types' => json_encode([$row->provider_type])]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('provider_types');
        });
    }
};
