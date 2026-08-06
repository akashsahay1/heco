<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            // The host it runs at. Nullable because losing the host must not
            // take the listing with it — HCT reassigns instead.
            $table->foreignId('hlh_id')->nullable()->constrained('service_providers')->nullOnDelete();
            // Who authored it, which is not always who hosts it: an OSP may
            // file an experience that runs at someone else's place.
            $table->foreignId('owner_provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->string('owner_type', 10)->nullable();
            // Nullable through this block: a member can save a half-finished
            // listing as a draft, and these are the answers they most often do
            // not have in the first sitting.
            $table->foreignId('region_id')->nullable()->constrained('regions');
            $table->foreignId('regenerative_project_id')->nullable()->constrained('regenerative_projects')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 100)->nullable();
            // Which of the three structural categories this is — it decides
            // which fields the form even shows.
            $table->string('category')->nullable()->index();
            $table->string('short_description', 500)->nullable();
            $table->text('long_description')->nullable();
            $table->text('unique_description')->nullable();
            $table->text('cultural_context')->nullable();
            $table->enum('duration_type', ['less_than_day', 'single_day', 'multi_day'])->nullable();
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->integer('duration_days')->nullable();
            $table->integer('duration_nights')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('includes_accommodation')->default(false);
            $table->string('accommodation_category', 50)->nullable();
            // What the place sleeps. Distinct from group_size_max, which is the
            // largest party the experience will take.
            $table->unsignedSmallInteger('total_rooms')->nullable();
            $table->unsignedSmallInteger('total_guests')->nullable();
            $table->boolean('includes_meals_breakfast')->default(false);
            $table->boolean('includes_meals_lunch')->default(false);
            $table->boolean('includes_meals_dinner')->default(false);
            $table->boolean('includes_guide')->default(false);
            $table->boolean('includes_transport')->default(false);
            $table->decimal('start_latitude', 10, 8)->nullable();
            $table->decimal('start_longitude', 11, 8)->nullable();
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();
            $table->string('area')->nullable();
            $table->boolean('trekking_required')->default(false);
            $table->boolean('road_seasonal_closure')->default(false);
            $table->integer('altitude_max')->nullable();
            $table->integer('altitude_min')->nullable();
            $table->enum('difficulty_level', ['easy', 'moderate', 'challenging', 'extreme'])->default('easy');
            $table->text('fitness_requirements')->nullable();
            $table->integer('age_min')->nullable();
            $table->integer('age_max')->nullable();
            $table->integer('group_size_min')->default(1);
            $table->integer('group_size_max')->nullable();
            $table->text('weather_dependency')->nullable();
            $table->text('cultural_sensitivities')->nullable();
            $table->text('environmental_constraints')->nullable();
            $table->string('best_seasons')->nullable();
            $table->string('available_months')->nullable();
            $table->string('restricted_months')->nullable();
            $table->string('unavailable_months')->nullable();
            $table->text('seasonality_notes')->nullable();
            $table->decimal('base_cost_per_person', 10, 2)->default(0);
            $table->char('price_currency', 3)->default('INR');
            $table->decimal('cost_accommodation', 10, 2)->default(0);
            $table->decimal('cost_logistics', 10, 2)->default(0);
            $table->decimal('cost_guide', 10, 2)->default(0);
            $table->decimal('cost_activities', 10, 2)->default(0);
            $table->decimal('cost_other', 10, 2)->default(0);
            $table->text('seasonal_price_variation')->nullable();
            $table->decimal('single_supplement', 10, 2)->default(0);
            $table->boolean('osps_involved')->default(false);
            $table->text('osp_services')->nullable();
            $table->text('traveller_bring_list')->nullable();
            $table->text('clothing_recommendations')->nullable();
            $table->text('health_notes')->nullable();
            $table->text('connectivity_notes')->nullable();
            $table->text('cultural_etiquette')->nullable();
            $table->text('operational_risks')->nullable();
            $table->text('past_issues')->nullable();
            $table->text('backup_options')->nullable();
            $table->text('emergency_notes')->nullable();
            $table->string('card_image')->nullable();
            $table->text('gallery')->nullable();
            $table->boolean('is_active')->default(true);

            // The review lifecycle. A plain string rather than an enum: 'draft'
            // joined it later, more states will follow, and every driver spells
            // an enum change differently — the allowed values are enforced in
            // the application, where they already were.
            $table->string('approval_status', 20)->default('approved')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // An edit to a listing that is already selling waits here until HCT
            // reviews it, so the live version keeps selling untouched.
            $table->json('pending_changes')->nullable();
            $table->timestamp('pending_submitted_at')->nullable();
            $table->foreignId('pending_submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['owner_provider_id', 'owner_type'], 'experiences_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
