<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_id', 20)->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('trip_name')->nullable();
            $table->enum('status', ['not_confirmed', 'confirmed', 'running', 'completed', 'cancelled'])->default('not_confirmed');
            $table->enum('stage', ['open', 'closed'])->default('open');
            $table->enum('traveller_origin', ['indian', 'foreigner'])->nullable();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('infants')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->string('pickup_location')->nullable();
            $table->time('pickup_time')->nullable();
            $table->string('drop_location')->nullable();
            $table->time('drop_time')->nullable();
            $table->text('operations_notes')->nullable();
            $table->string('accommodation_comfort', 50)->nullable();
            $table->string('vehicle_comfort', 50)->nullable();
            $table->string('guide_preference', 50)->nullable();
            $table->string('travel_pace', 50)->nullable();
            $table->string('budget_sensitivity', 50)->nullable();
            $table->text('other_preferences')->nullable();
            $table->decimal('transport_cost', 12, 2)->default(0);
            $table->decimal('accommodation_cost', 12, 2)->default(0);
            $table->decimal('guide_cost', 12, 2)->default(0);
            $table->decimal('activity_cost', 12, 2)->default(0);
            $table->decimal('other_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('margin_rp_percent', 5, 2)->default(0);
            $table->decimal('margin_rp_amount', 12, 2)->default(0);
            $table->decimal('margin_hrp_percent', 5, 2)->default(0);
            $table->decimal('margin_hrp_amount', 12, 2)->default(0);
            $table->decimal('commission_hct_percent', 5, 2)->default(0);
            $table->decimal('commission_hct_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2)->default(0);
            $table->longText('ai_raw_response')->nullable();
            $table->text('general_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
