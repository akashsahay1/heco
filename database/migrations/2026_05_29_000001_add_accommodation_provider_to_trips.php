<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accommodation is no longer priced by a flat category multiplier. The
     * traveller now picks a category, then a specific provider within that
     * category, and the chosen provider's sp_pricing row drives the price.
     * We store both the provider and the exact pricing row so the cost engine
     * can resolve the real rate (and we keep provider_id for reporting).
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('accommodation_provider_id')->nullable()->after('accommodation_comfort')
                ->constrained('service_providers')->nullOnDelete();
            $table->foreignId('accommodation_pricing_id')->nullable()->after('accommodation_provider_id')
                ->constrained('sp_pricing')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accommodation_pricing_id');
            $table->dropConstrainedForeignId('accommodation_provider_id');
        });
    }
};
