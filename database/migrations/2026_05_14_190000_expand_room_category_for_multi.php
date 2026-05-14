<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // room_category now holds an optional comma-separated list of room
        // types offered at one comfort tier ("Single Room, Double Room"),
        // so 100 chars is too tight. Bump to 255.
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('room_category', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('room_category', 100)->nullable()->change();
        });
    }
};
