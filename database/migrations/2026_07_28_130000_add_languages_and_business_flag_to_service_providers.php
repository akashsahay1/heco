<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two signup answers the form had no home for.
 *
 * SPOKEN LANGUAGES (screen 6). Which languages a member speaks decides whether
 * they can host a foreign traveller unaided, so it is asked at signup rather
 * than discovered on the ground. English and Hindi are asked as plain yes/no
 * because those are the two that change what HECO can offer; anything else is
 * free text, per the client — a fixed list would only omit the language that
 * mattered.
 *
 * HAS A BUSINESS (screen 7). "Do you already have a business? Yes → fill in the
 * details. No → skip to the next screen." The business block used to be
 * unconditional, which asked a homestay owner with no registered company to
 * pick a business type that did not describe them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->boolean('speaks_english')->default(false)->after('phone_2');
            $table->boolean('speaks_hindi')->default(false)->after('speaks_english');
            $table->string('other_languages')->nullable()->after('speaks_hindi');
            // Nullable on purpose: null means "never asked", which is different
            // from a member who answered No.
            $table->boolean('has_business')->nullable()->after('provider_types');
        });

        // Rows created before the question existed: treat any business detail on
        // file as a Yes, everything else stays null rather than guessing No.
        DB::table('service_providers')
            ->where(function ($q) {
                $q->whereNotNull('business_type')
                  ->orWhereNotNull('registration_number')
                  ->orWhereNotNull('year_established');
            })
            ->update(['has_business' => true]);
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['speaks_english', 'speaks_hindi', 'other_languages', 'has_business']);
        });
    }
};
