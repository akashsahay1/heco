<?php

use App\Models\Experience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three changes the client asked for, all about how an HLH describes what they
 * offer.
 *
 * 1. CATEGORY. "Rather than using a single form for everyone, the user should
 *    first choose the category that best describes their experience, and then
 *    be presented with a form specifically designed for that category." The
 *    three categories are structural — they decide which fields exist at all —
 *    and are a different axis from the existing `type` column, which is a theme
 *    (Trek, Cultural Immersion, Spiritual…). Both stay.
 *
 * 2. DRAFTS. "The experience creation form should allow users to save drafts and
 *    continue editing later. Many users won't have all the information or photos
 *    ready in one session." A draft is a state in the same lifecycle as pending
 *    and approved, so it joins the enum rather than becoming a second flag that
 *    could contradict it. Crucially, scopePending() must not pick drafts up —
 *    they have not been submitted and are not HCT's to review.
 *
 * 3. ADD-ONS. "An HLH should be able to enrich their main experience with
 *    optional complementary experiences" — a village walk, a cooking class,
 *    birdwatching. Their own table because there are many per experience and
 *    each carries a price. Per the data-collection PDF they belong to
 *    Experiential accommodation and Guided Cultural & Outdoor Activities, not to
 *    Workshops — that rule is enforced in the form, not the schema, so an
 *    existing add-on is never silently destroyed by a category change.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type')->index();
        });

        // Existing rows predate categories. They are left NULL rather than
        // guessed: an experience with an itinerary might be a guided activity or
        // a workshop, and mislabelling one silently is worse than leaving HCT to
        // set it.

        // approval_status becomes a plain string so 'draft' can join the
        // lifecycle. A database enum would have to be altered on every future
        // state and is spelled differently on each driver — MySQL wants a raw
        // MODIFY, SQLite enforces a CHECK constraint it cannot alter in place —
        // so the allowed values are enforced in the application instead, where
        // they already were.
        Schema::table('experiences', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('approved')->change();
        });

        Schema::create('experience_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Nullable: a host may offer something complimentary, and "free" is
            // a different statement from "we have not priced it yet".
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['experience_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_addons');

        // Anything still sitting in draft has no valid state to fall back to.
        Experience::where('approval_status', 'draft')->update(['approval_status' => 'pending']);

        Schema::table('experiences', function (Blueprint $table) {
            $table->enum('approval_status', ['approved', 'pending', 'rejected'])
                ->default('approved')->change();
            $table->dropColumn('category');
        });
    }
};
