<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            // Optional verification documents captured during the multi-step
            // application (ID proof, registration, permits, photos). Stored as
            // an array of { label, path, original_name }.
            $table->json('documents')->nullable()->after('activity_types');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('documents');
        });
    }
};
