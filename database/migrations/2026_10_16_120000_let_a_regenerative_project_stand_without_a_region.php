<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's team: a regenerative project has nothing to do with a region,
 * and any project may go on any trip. New projects are made without one;
 * those already saved keep theirs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('regenerative_projects', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('regenerative_projects', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable(false)->change();
        });
    }
};
