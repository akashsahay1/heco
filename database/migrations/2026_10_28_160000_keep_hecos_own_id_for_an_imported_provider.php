<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ID HECO already gives a service provider in its own records ("SP ID"),
 * carried in when providers are imported from a CSV (feedback, 2026-10-28), so
 * a provider is the same one in both places and is never imported twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('sp_code', 50)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropUnique(['sp_code']);
            $table->dropColumn('sp_code');
        });
    }
};
