<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The handler has always treated `unit` as optional for an accommodation rate
 * — a room is priced per night whether or not anyone types it — but the column
 * was NOT NULL, so a save that left it out died with a constraint violation
 * rather than a validation message.
 *
 * The same now applies to a rental, and to a taxi quoted per kilometre where
 * the rate itself already says what the unit is. Making the column nullable
 * matches what the validation rules already promise; every form that does send
 * a unit is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->string('unit', 50)->nullable(false)->change();
        });
    }
};
