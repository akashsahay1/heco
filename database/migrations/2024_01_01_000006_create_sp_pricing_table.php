<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            // A string, not an enum: the list of things a supplier can sell has
            // grown twice already, and every driver spells an enum change
            // differently. The values live in the service_type SystemList.
            $table->string('service_type', 30);
            $table->string('category', 100)->nullable();

            // ── Accommodation ────────────────────────────────────────────
            // Holds a comma-separated list of room types offered at one comfort
            // tier ("Single Room, Double Room"), so it is wider than a name.
            $table->string('room_category', 255)->nullable();
            $table->string('comfort_tier', 80)->nullable()->index();
            $table->unsignedSmallInteger('total_rooms')->nullable();
            $table->string('default_occupancy', 50)->nullable();

            $table->string('description')->nullable();
            // Nullable: some rates are quoted per km or per day by their own
            // fields below, and inventing a unit for those states nothing.
            $table->string('unit', 50)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('meal_plan', 100)->nullable();

            // ── Transport ────────────────────────────────────────────────
            $table->string('vehicle_type', 100)->nullable();
            // Two per-km rates, because a hill kilometre costs more to drive
            // than a plains one.
            $table->boolean('ac_available')->nullable();
            $table->unsignedSmallInteger('vehicle_count')->nullable();
            $table->decimal('price_per_km_plains', 10, 2)->nullable();
            $table->decimal('price_per_km_hills', 10, 2)->nullable();
            $table->decimal('ac_extra_cost', 10, 2)->nullable();
            $table->string('vehicle_make_model', 120)->nullable();
            $table->string('vehicle_registration_no', 40)->nullable();
            $table->unsignedSmallInteger('vehicle_year')->nullable();
            // Stored paths, like experiences.gallery.
            $table->json('vehicle_photos')->nullable();
            $table->unsignedSmallInteger('vehicle_capacity')->nullable();
            $table->decimal('driver_allowance', 10, 2)->nullable();
            // What the quoted rate covers. Nullable rather than defaulted so a
            // row reads as "not stated" instead of a claim nobody made.
            $table->boolean('driver_included')->nullable();
            $table->boolean('fuel_tolls_extra')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();

            // ── Activity and guide ───────────────────────────────────────
            $table->unsignedSmallInteger('min_group')->nullable();
            $table->unsignedSmallInteger('max_group')->nullable();
            $table->text('specialties')->nullable();
            // `price` stays the one-day wage; a multi-day booking with a night
            // away is a different rate, not a multiple of it.
            $table->boolean('speaks_english')->nullable();
            $table->json('languages')->nullable();
            $table->decimal('wage_multi_day', 10, 2)->nullable();
            $table->boolean('is_certified')->nullable();
            $table->boolean('has_first_aid')->nullable();

            // ── Rental ───────────────────────────────────────────────────
            // Charges per day are the row's own price; what is being rented and
            // what is held against it are not.
            $table->string('rental_item', 150)->nullable();
            $table->decimal('security_deposit', 10, 2)->nullable();

            // ── Standard accommodation ───────────────────────────────────
            // A hotel is a place before it is a rate, so it needs to say where
            // it is and how many it sleeps.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('guest_capacity')->nullable();
            $table->text('seasonality_notes')->nullable();
            $table->json('photos')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // ── Review lifecycle ─────────────────────────────────────────
            // An edit to a live rate is filed as its own row pointing back at
            // the one it would replace, so the live rate keeps selling until
            // HCT accepts the change.
            $table->enum('approval_status', ['approved', 'pending', 'rejected'])->default('approved')->index();
            $table->foreignId('pending_for_id')->nullable()->constrained('sp_pricing')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_pricing');
    }
};
