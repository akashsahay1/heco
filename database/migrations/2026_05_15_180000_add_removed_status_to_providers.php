<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Soft-archive support for service providers.
 *
 * Adds 'removed' as an enum value on service_providers.status. A removed
 * provider is preserved (so historical trips, payments, and references stay
 * intact) but is excluded from active listings, cannot log in, and their
 * pricing/availability is hidden from trip-manager / traveller views.
 *
 * Admin can flip the status back to 'approved' to restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't support modifying enum cleanly via Schema Builder;
        // raw ALTER is the standard approach.
        DB::statement("ALTER TABLE service_providers MODIFY COLUMN status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Roll any 'removed' rows back to 'rejected' before shrinking the enum
        // so the down migration doesn't fail.
        DB::table('service_providers')->where('status', 'removed')->update(['status' => 'rejected']);
        DB::statement("ALTER TABLE service_providers MODIFY COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
};
