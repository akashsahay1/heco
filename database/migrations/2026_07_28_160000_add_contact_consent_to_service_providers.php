<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a member agrees to be contacted about their application.
 *
 * The client's reason for asking beyond in-app notifications: "many users won't
 * regularly check their email" — so WhatsApp/SMS has to be a first-class
 * channel, not an afterthought. Both default to true because the risk here is
 * an approval notice nobody sees, not over-contacting.
 *
 * Existing rows are backfilled to true: they applied before the question
 * existed and HECO has been contacting them by email regardless.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->boolean('contact_by_email')->default(true)->after('other_languages');
            $table->boolean('contact_by_whatsapp')->default(true)->after('contact_by_email');
        });
    }

    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['contact_by_email', 'contact_by_whatsapp']);
        });
    }
};
