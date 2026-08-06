<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->integer('day_number');
            $table->date('date')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('day_type', 20)->default('activity');
            // Whether the itinerary put this day here or a person did — an
            // AI-built day and one HCT inserted are edited differently.
            $table->string('added_by', 20)->default('system');
            $table->boolean('is_experience_day')->default(false);
            $table->unsignedBigInteger('experience_group_id')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_days');
    }
};
