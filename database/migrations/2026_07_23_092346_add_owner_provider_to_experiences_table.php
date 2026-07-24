<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Experiences are now authored by the providers themselves (HLH and OSP)
 * rather than by HCT. `hlh_id` could only ever point at the homestay/lodge
 * host, so it cannot express "this experience belongs to an OSP".
 *
 * `owner_provider_id` + `owner_type` carry ownership from here on. `hlh_id`
 * stays as-is — it is still the HLH the experience runs out of, and existing
 * rows keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_provider_id')->nullable()->after('hlh_id');
            $table->string('owner_type', 10)->nullable()->after('owner_provider_id');

            $table->foreign('owner_provider_id')
                ->references('id')->on('service_providers')
                ->nullOnDelete();

            $table->index(['owner_provider_id', 'owner_type'], 'experiences_owner_idx');
        });

        // Backfill: every experience that exists today was authored against an
        // HLH, so that provider becomes its owner.
        DB::table('experiences')
            ->whereNotNull('hlh_id')
            ->update([
                'owner_provider_id' => DB::raw('hlh_id'),
                'owner_type'        => 'hlh',
            ]);
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['owner_provider_id']);
            $table->dropIndex('experiences_owner_idx');
            $table->dropColumn(['owner_provider_id', 'owner_type']);
        });
    }
};
