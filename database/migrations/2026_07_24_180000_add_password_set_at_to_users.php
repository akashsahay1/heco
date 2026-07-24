<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks accounts whose owner actually chose their password.
 *
 * Providers who sign up now pick a password on the form, while admin-created
 * providers get an unusable random secret. Approval needs to tell the two apart:
 * the first gets a plain "you're approved" email, the second needs a
 * set-password link.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Everyone who exists today has been using their account, so treat their
        // password as chosen — otherwise approval would mail them a reset link.
        DB::table('users')->whereNull('password_set_at')->update(['password_set_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
