<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('continent', 100)->nullable()->after('description');
            $table->string('country', 100)->nullable()->after('continent');
            $table->dropColumn('state');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('state', 100)->nullable()->after('description');
            $table->dropColumn(['continent', 'country']);
        });
    }
};
