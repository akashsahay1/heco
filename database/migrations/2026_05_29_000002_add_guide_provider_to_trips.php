<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror of accommodation_provider_id/pricing_id, for the Guide preference:
     * traveller picks a guide category, then a specific approved guide provider,
     * and that provider's sp_pricing (per-day) rate drives the guide cost.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('guide_provider_id')->nullable()->after('guide_preference')
                ->constrained('service_providers')->nullOnDelete();
            $table->foreignId('guide_pricing_id')->nullable()->after('guide_provider_id')
                ->constrained('sp_pricing')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guide_pricing_id');
            $table->dropConstrainedForeignId('guide_provider_id');
        });
    }
};
