<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('experience_room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            // From the room_category and meal_plan SystemLists, so HCT can edit
            // the options without a deploy.
            $table->string('occupancy');
            $table->string('meal_plan');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // One price per occupancy + meal plan. Two rows for the same pair
            // would leave the grid with no answer for that cell.
            $table->unique(['experience_id', 'occupancy', 'meal_plan'], 'exp_room_rate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_room_rates');
    }
};
