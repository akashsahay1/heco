<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make experiences.hlh_id nullable so a provider can be permanently
 * deleted without orphaning the FK. Also switch the constraint to
 * nullOnDelete so deleting a provider auto-detaches their experiences
 * (the row stays, hlh_id goes NULL, app code marks them inactive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['hlh_id']);
            $table->unsignedBigInteger('hlh_id')->nullable()->change();
            $table->foreign('hlh_id')
                ->references('id')->on('service_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Cannot roll back cleanly if any experiences have NULL hlh_id
        // (the original column was NOT NULL). Set any NULLs to the first
        // approved HLH before re-imposing the NOT NULL + RESTRICT FK.
        $fallback = \DB::table('service_providers')
            ->where('provider_type', 'hlh')
            ->where('status', 'approved')
            ->value('id');
        if ($fallback) {
            \DB::table('experiences')->whereNull('hlh_id')->update(['hlh_id' => $fallback]);
        }
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['hlh_id']);
            $table->unsignedBigInteger('hlh_id')->nullable(false)->change();
            $table->foreign('hlh_id')->references('id')->on('service_providers');
        });
    }
};
