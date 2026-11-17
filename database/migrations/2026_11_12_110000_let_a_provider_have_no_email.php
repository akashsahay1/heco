<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Most of HECO's existing providers have no email; the phone is the one
 * contact every provider must have (answers, 2026-11-12, Q2). Still unique
 * where it is given: MySQL lets any number of rows leave it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
