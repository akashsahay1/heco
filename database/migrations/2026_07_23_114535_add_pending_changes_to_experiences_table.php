<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a provider revise an already-approved experience without pulling it out
 * of circulation.
 *
 * The edit is parked in `pending_changes` (the submitted payload, verbatim)
 * while the live row carries on being sold exactly as approved. HCT approving
 * it replays the payload through the normal save path; rejecting it simply
 * discards the column and the live row was never touched.
 *
 * Deliberately not a shadow row like sp_pricing uses: an experience owns days
 * and price slabs and is referenced by trips, so a duplicate could leak into
 * listings or itineraries. A column cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->json('pending_changes')->nullable()->after('rejection_reason');
            $table->timestamp('pending_submitted_at')->nullable()->after('pending_changes');
            $table->foreignId('pending_submitted_by')->nullable()
                ->after('pending_submitted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['pending_submitted_by']);
            $table->dropColumn(['pending_changes', 'pending_submitted_at', 'pending_submitted_by']);
        });
    }
};
