<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Experience approval workflow.
 *
 * HLH and OSP providers author their own experiences; HCT reviews them before
 * travellers can see them.
 *
 *   approval_status:
 *     - approved → live (subject to is_active)
 *     - pending  → awaiting HCT review; kept out of circulation
 *     - rejected → declined with a reason; the provider can revise and resubmit
 *
 * Unlike sp_pricing this uses no shadow "pending edit" row: an experience owns
 * days and price slabs and is referenced by trips, so duplicating one to hold
 * an unapproved edit would mean duplicating that whole tree. Instead a provider
 * save returns the row to `pending` and deactivates it until HCT approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->enum('approval_status', ['approved', 'pending', 'rejected'])
                ->default('approved')->after('is_active');
            $table->timestamp('submitted_at')->nullable()->after('approval_status');
            $table->foreignId('submitted_by')->nullable()
                ->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()
                ->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index('approval_status');
        });

        // Everything that exists today was created by HCT, so it is already
        // reviewed — flipping it to pending would empty the catalogue.
        DB::table('experiences')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'approval_status', 'submitted_at', 'submitted_by',
                'approved_at', 'approved_by', 'rejection_reason',
            ]);
        });
    }
};
