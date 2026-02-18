<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_day_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained('trip_days')->cascadeOnDelete();
            $table->foreignId('experience_id')->constrained('experiences');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('cost_per_person', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_day_experiences');
    }
};
