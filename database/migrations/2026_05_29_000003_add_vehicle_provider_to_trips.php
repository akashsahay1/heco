<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror of accommodation/guide provider columns, for the Vehicle preference:
     * traveller picks a vehicle type, then a specific approved transport provider.
     * Transport is priced per km and trips store no distance, so the chosen
     * provider + rate are recorded for operations/display; the trip's transport
     * cost is not auto-derived from it (no distance to multiply).
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('vehicle_provider_id')->nullable()->after('vehicle_comfort')
                ->constrained('service_providers')->nullOnDelete();
            $table->foreignId('vehicle_pricing_id')->nullable()->after('vehicle_provider_id')
                ->constrained('sp_pricing')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_pricing_id');
            $table->dropConstrainedForeignId('vehicle_provider_id');
        });
    }
};
