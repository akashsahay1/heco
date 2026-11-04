<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How many days before a trip a part-paid balance comes back on the Dashboard
 * (HECO's feedback, 2026-10-28: "a set number of days before the trip ... a
 * setting that we can change"). The default is a placeholder until HECO names
 * theirs; it is edited in Control Panel > Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'payment_reminder_days_before_trip')->exists()) {
            DB::table('settings')->insert([
                'key' => 'payment_reminder_days_before_trip',
                'value' => '7',
                'group' => 'financial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'payment_reminder_days_before_trip')->delete();
    }
};
