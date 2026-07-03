<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds 'meal' to sp_pricing.service_type so service providers can price meals
    // (day services already allow 'meal'). Uses native ->change() so it works on
    // both MySQL (enum) and sqlite (varchar+check) — no data is touched.
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->enum('service_type', ['accommodation', 'transport', 'guide', 'activity', 'meal', 'other'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->enum('service_type', ['accommodation', 'transport', 'guide', 'activity', 'other'])->change();
        });
    }
};
