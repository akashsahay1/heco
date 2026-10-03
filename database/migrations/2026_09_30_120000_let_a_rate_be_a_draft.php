<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A rate a member has not finished yet.
 *
 * An experience has had this from the start: Save draft keeps what has been
 * written without sending it to HCT, and the form's own rules are not applied
 * until it is submitted. A rate had nothing of the kind. Its required boxes -
 * a vehicle's type, a room's tier, the price - are checked the moment Save is
 * pressed, so a member who has half of it, or who is told a price tomorrow,
 * loses everything they typed.
 *
 * `draft` is added beside the three that were here. Nothing else needs to
 * change to keep drafts out of anybody's way: every query that matters names
 * the status it wants - `approved` for what a trip may use and what a
 * traveller sees, `pending` for HCT's review queue - so a draft is invisible
 * to all of them by simply not being either.
 *
 * The column is an enum rather than a string, so this is a MODIFY. MySQL
 * keeps every row's value where it is; no data moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE sp_pricing MODIFY approval_status "
            . "ENUM('approved', 'pending', 'rejected', 'draft') NOT NULL DEFAULT 'approved'"
        );
    }

    public function down(): void
    {
        // Anything still a draft becomes pending rather than being lost: it is
        // somebody's unfinished work, and HCT can see it and ask about it.
        DB::table('sp_pricing')->where('approval_status', 'draft')->update([
            'approval_status' => 'pending',
        ]);

        DB::statement(
            "ALTER TABLE sp_pricing MODIFY approval_status "
            . "ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved'"
        );
    }
};
