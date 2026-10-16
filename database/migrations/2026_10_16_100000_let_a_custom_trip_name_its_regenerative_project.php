<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A trip HCT builds from custom days has no experience to bring a
 * regenerative project with it, so HCT names one on the trip. Without one
 * the trip carries no RP margin.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('regenerative_project_id')->nullable()->after('commission_hct_amount')
                ->constrained('regenerative_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regenerative_project_id');
        });
    }
};
