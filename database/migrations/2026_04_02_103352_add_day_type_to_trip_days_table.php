<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->string('day_type', 20)->default('activity')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('trip_days', function (Blueprint $table) {
            $table->dropColumn('day_type');
        });
    }
};
