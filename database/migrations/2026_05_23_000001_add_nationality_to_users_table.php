<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Country of citizenship (stored verbatim from config/countries.php).
            // Distinct from `country` (residence/address). Drives the trip
            // traveller_origin pricing bucket: Indian vs foreigner.
            $table->string('nationality', 100)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nationality');
        });
    }
};
