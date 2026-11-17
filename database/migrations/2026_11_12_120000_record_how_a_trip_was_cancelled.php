<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the team says when they cancel a trip, and the traveller's credit
 * (answers, 2026-11-12, Q4).
 *
 * The status stays 'cancelled' either way; cancel_type says whether it was
 * called off or postponed with credit. credit_kept is money of the
 * traveller's that HECO holds from this trip; credit_used is credit taken
 * off this trip's Final Price. What a traveller has left is the one summed
 * less the other, over their trips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('cancel_type', 20)->nullable()->after('status');
            $table->text('cancel_reason')->nullable()->after('cancel_type');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('cancelled_at');
            $table->decimal('credit_kept', 12, 2)->default(0)->after('refund_amount');
            $table->decimal('credit_used', 12, 2)->default(0)->after('credit_kept');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['cancel_type', 'cancel_reason', 'cancelled_at', 'refund_amount', 'credit_kept', 'credit_used']);
        });
    }
};
