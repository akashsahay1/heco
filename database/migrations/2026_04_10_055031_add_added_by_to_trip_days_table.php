<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->string('added_by', 20)->default('system')->after('day_type');
        });
    }

    public function down(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->dropColumn('added_by');
        });
    }
};
