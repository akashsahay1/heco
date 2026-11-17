<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HECO named the number (answers, 2026-11-12, Q8): a part-paid balance comes
 * back on the Dashboard 10 days before the trip, still editable in Control
 * Panel > Settings. Only the old placeholder of 7 is replaced; a value
 * somebody has already typed is theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'payment_reminder_days_before_trip')
            ->where('value', '7')
            ->update(['value' => '10', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'payment_reminder_days_before_trip')
            ->where('value', '10')
            ->update(['value' => '7', 'updated_at' => now()]);
    }
};
