<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lead is an enquiry somebody made, and nothing more.
 *
 * Filing one used to open three rows at once: a traveller account, a trip, and
 * the lead against them. That put a trip on the books for a conversation that
 * might come to nothing, and it meant the enquiry's own details - who rang,
 * from where, for how many, when - had no home of their own. The name, email
 * and phone lived on the account that was created; the region, party and dates
 * lived on the trip.
 *
 * A lead now holds them itself. It needs no account and no trip: it is
 * remembered data, so that somebody can be rung back. The trip arrives later,
 * when that person signs up with the same email and builds one, and the lead
 * is won when they pay for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Who enquired. The email is the one that matters: it is what a
            // lead is later matched to a traveller by.
            $table->string('full_name', 150)->nullable()->after('id');
            $table->string('email', 150)->nullable()->after('full_name');
            $table->string('mobile', 30)->nullable()->after('email');

            // What they asked about.
            $table->unsignedBigInteger('region_id')->nullable()->after('mobile');
            $table->unsignedSmallInteger('adults')->nullable()->after('region_id');
            $table->unsignedSmallInteger('children')->nullable()->after('adults');
            $table->date('start_date')->nullable()->after('children');
            $table->date('end_date')->nullable()->after('start_date');

            // Matching runs on every traveller payment, so it is worth an index.
            $table->index('email', 'leads_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_email_index');
            $table->dropColumn([
                'full_name', 'email', 'mobile',
                'region_id', 'adults', 'children', 'start_date', 'end_date',
            ]);
        });
    }
};
