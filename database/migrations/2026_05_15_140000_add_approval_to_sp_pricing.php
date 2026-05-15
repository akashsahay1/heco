<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SP pricing approval workflow.
 *
 *   approval_status:
 *     - approved  → live, used by trip-manager / traveller / cost calc
 *     - pending   → awaiting admin review (NEW row or edit of an approved row)
 *     - rejected  → admin declined; SP can resubmit
 *
 *   pending_for_id:
 *     - NULL → this is a NEW row submitted by the SP
 *     - INT  → this row is a pending EDIT of the row with id=pending_for_id;
 *              the target row stays live until admin approves, then this row
 *              is squashed into the target and deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->enum('approval_status', ['approved', 'pending', 'rejected'])
                  ->default('approved')->after('is_active');
            $table->foreignId('pending_for_id')->nullable()
                  ->after('approval_status')
                  ->constrained('sp_pricing')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('pending_for_id');
            $table->foreignId('submitted_by')->nullable()
                  ->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()
                  ->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index('approval_status');
        });

        // Every existing row is treated as approved so the flow doesn't
        // retroactively disable live inventory.
        DB::table('sp_pricing')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('sp_pricing', function (Blueprint $table) {
            $table->dropForeign(['pending_for_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'approval_status', 'pending_for_id',
                'submitted_at', 'submitted_by',
                'approved_at', 'approved_by',
                'rejection_reason',
            ]);
        });
    }
};
