<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A provider sometimes gives money back after a cancellation, by their own
 * cancellation policy (HECO, 2026-11-14). An entry is now either money HECO
 * paid the provider or money the provider returned; what HECO has paid them
 * on a bill is the one less the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_payment_entries', function (Blueprint $table) {
            $table->string('direction', 10)->default('paid')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('sp_payment_entries', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
