<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'budget_notes')) {
                $table->text('budget_notes')->nullable()->after('budget_sensitivity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'budget_notes')) {
                $table->dropColumn('budget_notes');
            }
        });
    }
};
