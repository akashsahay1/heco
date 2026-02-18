<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_day_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained('trip_days')->cascadeOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->enum('service_type', ['accommodation', 'transport', 'guide', 'activity', 'meal', 'other']);
            $table->string('description')->nullable();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->boolean('is_included')->default(true);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_day_services');
    }
};
