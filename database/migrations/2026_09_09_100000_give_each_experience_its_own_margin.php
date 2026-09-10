<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What HECO adds to an experience before a traveller sees the price.
 *
 * A provider already has one — `service_providers.markup_percent`, applied by
 * CostCalculatorService to every rate they are pinned for — and the experience
 * bundle had none, so a listing filed by a host reached travellers at exactly
 * the price the host wrote. This is the same idea, kept per experience rather
 * than per host because that is what was asked for: two experiences run by the
 * same homestay can carry different margins.
 *
 * Null means "not set here", and the global default_experience_markup_percent
 * setting answers instead — the same fallback service_providers uses, so HCT
 * can set one number rather than open two hundred listings.
 *
 * The raw figure stays exactly where it was. It is what the host is owed and
 * what HCT sees on the form; the marked-up one is a traveller-facing number and
 * is never written back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->decimal('markup_percent', 5, 2)->nullable()->after('price_currency');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn('markup_percent');
        });
    }
};
