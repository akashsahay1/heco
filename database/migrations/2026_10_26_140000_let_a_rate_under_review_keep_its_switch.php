<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a partner chose for a rate's Active switch while HCT reviews it.
 *
 * A rate under review has to stay switched off, because inventory and matching
 * read `is_active` alone, so the choice had nowhere to live: approval switched
 * every new rate on, and an edit could never switch a live one off. This holds
 * the choice until approval applies it, and is empty again afterwards.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->boolean('requested_active')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropColumn('requested_active');
        });
    }
};
