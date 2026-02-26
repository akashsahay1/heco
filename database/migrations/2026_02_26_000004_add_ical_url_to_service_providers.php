<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('ical_url', 500)->nullable()->after('notes');
            $table->timestamp('ical_last_synced_at')->nullable()->after('ical_url');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['ical_url', 'ical_last_synced_at']);
        });
    }
};
