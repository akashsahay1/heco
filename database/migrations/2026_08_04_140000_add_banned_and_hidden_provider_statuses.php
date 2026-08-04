<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two more things a provider can be, neither of which "rejected" covered.
 *
 * 'banned'  — HCT has blocked them. The linked login is deactivated, so they
 *             cannot reach the app or the portal, and the row stays so the
 *             address cannot simply re-apply its way back in.
 * 'hidden'  — temporarily out of service, not in trouble. They keep their
 *             login and can manage rates and availability; they are just not
 *             offered to travellers, matching, or Trip Manager until they are
 *             approved again.
 *
 * Everything downstream gates on status === 'approved', so both are excluded
 * from listings by definition — see SpMiddleware for the one place that has to
 * tell them apart.
 *
 * 'removed' stays in the enum only because rows written by the old soft-delete
 * still carry it. Nothing sets it any more: removing a provider deletes the
 * row now, so the email is free to apply again.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE service_providers MODIFY COLUMN status ENUM('pending','approved','rejected','removed','banned','hidden') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Park them somewhere the narrower enum can hold before shrinking it:
        // a banned provider is closest to rejected, a hidden one to pending.
        DB::table('service_providers')->where('status', 'banned')->update(['status' => 'rejected']);
        DB::table('service_providers')->where('status', 'hidden')->update(['status' => 'pending']);
        DB::statement("ALTER TABLE service_providers MODIFY COLUMN status ENUM('pending','approved','rejected','removed') NOT NULL DEFAULT 'pending'");
    }
};
