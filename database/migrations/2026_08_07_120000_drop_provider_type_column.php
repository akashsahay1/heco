<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `provider_type` goes; `provider_types` is the answer.
 *
 * The column named the primary role, and the set beside it named all of them.
 * Two columns for one fact, able to disagree — kept in step only by every
 * writer remembering to include the primary in the set. What reads it still
 * reads it: ServiceProvider::getProviderTypeAttribute() returns the first of
 * the set, so "the type to show when only one fits" survives as a derived
 * value rather than stored one.
 *
 * The API keeps sending provider_type for the same reason: builds already on
 * members' phones read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A database built from the baseline never had the column — it is only
        // older ones that are carrying it, and only they have anything to do.
        if (!Schema::hasColumn('service_providers', 'provider_type')) {
            return;
        }

        // A row whose set never got populated would otherwise lose its only
        // type. Rows created before provider_types existed are exactly that.
        DB::table('service_providers')
            ->whereNotNull('provider_type')
            ->where(fn ($q) => $q->whereNull('provider_types')->orWhere('provider_types', '[]'))
            ->orderBy('id')
            ->each(function ($provider) {
                DB::table('service_providers')
                    ->where('id', $provider->id)
                    ->update(['provider_types' => json_encode([$provider->provider_type])]);
            });

        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('provider_type');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->enum('provider_type', ['hrp', 'hlh', 'osp'])->nullable()->after('user_id');
        });

        // Back to what it always held: the first of the set.
        DB::table('service_providers')->orderBy('id')->each(function ($provider) {
            $types = json_decode($provider->provider_types ?? '[]', true) ?: [];

            DB::table('service_providers')
                ->where('id', $provider->id)
                ->update(['provider_type' => $types[0] ?? null]);
        });
    }
};
