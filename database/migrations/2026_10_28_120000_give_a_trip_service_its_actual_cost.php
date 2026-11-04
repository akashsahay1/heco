<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a service on a trip actually costs HECO, beside what the traveller is
 * charged for it.
 *
 * `cost` prices the trip for the traveller; `actual_cost` is what the provider
 * is paid, and is the figure the provider's bill is raised from. Empty means
 * "the same as cost", which is every line written before this existed, so no
 * trip's price and no bill already raised changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->decimal('actual_cost', 10, 2)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('trip_day_services', function (Blueprint $table) {
            $table->dropColumn('actual_cost');
        });
    }
};
