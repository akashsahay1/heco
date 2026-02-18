<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regenerative_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('region_id')->constrained('regions');
            $table->string('local_association')->nullable();
            $table->string('action_type');
            $table->text('short_description');
            $table->text('detailed_description')->nullable();
            $table->string('impact_unit');
            $table->text('conversion_rules')->nullable();
            $table->enum('measurement_frequency', ['one_time', 'periodic', 'cumulative'])->default('cumulative');
            $table->decimal('reference_budget', 12, 2)->nullable();
            $table->decimal('cost_per_impact_unit', 10, 2)->nullable();
            $table->text('active_periods')->nullable();
            $table->text('paused_periods')->nullable();
            $table->text('operational_constraints')->nullable();
            $table->string('main_image')->nullable();
            $table->text('gallery')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('fallback_for_regions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regenerative_projects');
    }
};
