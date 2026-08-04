<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An email is unique per role from here on, not outright.
 *
 * One person is often more than one thing to HECO — a traveller who later
 * hosts, or the admin who also books their own trips. A single unique index on
 * users.email made that impossible to express: giving the HCT login the same
 * address as an existing traveller failed on users_email_unique, and provider
 * approval worked around it by hijacking the traveller's row and overwriting
 * its user_role, which quietly cost that person their traveller identity.
 *
 * Because an email can now repeat, "find the user with this email" is no
 * longer a question with one answer. Every credential lookup narrows by the
 * roles its entry point serves — see User::findByCredentials().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'user_role'], 'users_email_role_unique');
        });
    }

    public function down(): void
    {
        // Only reversible while no address is shared across roles — the whole
        // point of the change. Clean up duplicates first if this has to run.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_role_unique');
            $table->unique('email', 'users_email_unique');
        });
    }
};
