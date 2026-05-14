<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('comfort_tier', 80)->nullable()->after('room_category');
            $table->index('comfort_tier');
        });

        // Backfill accommodation rows from each provider's accommodation_categories
        // (first element). Providers with multiple selected tiers will only seed
        // the first one onto their rows; admins/SPs can adjust per-row afterwards.
        $providers = DB::table('service_providers')
            ->whereNotNull('accommodation_categories')
            ->get(['id', 'accommodation_categories']);

        foreach ($providers as $sp) {
            $cats = json_decode($sp->accommodation_categories ?? '[]', true);
            if (!is_array($cats) || empty($cats)) continue;
            $first = $cats[0];
            DB::table('sp_pricing')
                ->where('service_provider_id', $sp->id)
                ->where('service_type', 'accommodation')
                ->whereNull('comfort_tier')
                ->update(['comfort_tier' => $first]);
        }
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropIndex(['comfort_tier']);
            $table->dropColumn('comfort_tier');
        });
    }
};
