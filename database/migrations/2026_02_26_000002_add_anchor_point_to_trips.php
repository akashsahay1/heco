<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('anchor_point', 200)->nullable()->after('end_location');
            $table->string('pickup_preference', 50)->nullable()->after('anchor_point');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['anchor_point', 'pickup_preference']);
        });
    }
};
