<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Per-provider admin markup (client req 3.3). The traveller-facing price of a
 * provider's service = raw price × (1 + markup_percent/100); the raw provider
 * price is never shown. Defaults to 0 (no markup until an admin sets one).
 * A global fallback lives in settings.default_provider_markup_percent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->decimal('markup_percent', 5, 2)->default(0)->after('status');
        });

        // Global fallback markup (0% by default) — admin-editable in Settings.
        DB::table('settings')->updateOrInsert(
            ['key' => 'default_provider_markup_percent'],
            ['value' => '0', 'group' => 'financial']
        );
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('markup_percent');
        });

        DB::table('settings')->where('key', 'default_provider_markup_percent')->delete();
    }
};
