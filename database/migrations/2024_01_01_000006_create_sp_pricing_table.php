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
            $table->enum('service_type', ['accommodation', 'transport', 'guide', 'activity', 'other']);
            $table->string('category', 100)->nullable();
            $table->string('description')->nullable();
            $table->string('unit', 50);
            $table->decimal('price', 10, 2);
            $table->string('meal_plan', 100)->nullable();
            $table->string('vehicle_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_pricing');
    }
};
