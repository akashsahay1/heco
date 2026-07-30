<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture for the provider.
 *
 * The app's profile header has always been able to show one — ProviderAccount
 * carries an avatarUrl and AppAvatar renders it — but nothing ever supplied it,
 * so every member saw their initials instead.
 *
 * It belongs on the provider rather than the user: the app shows the provider's
 * identity there (name, role, approval), so for a homestay this is the house or
 * its logo, and for a regional partner it is their own photo. users.avatar stays
 * what it is — the picture an OAuth login brought with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
