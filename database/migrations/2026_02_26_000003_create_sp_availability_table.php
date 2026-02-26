<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['booked', 'blocked'])->default('blocked');
            $table->enum('source', ['manual', 'ical', 'trip_assignment'])->default('manual');
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_day_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ical_uid')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['service_provider_id', 'date']);
            $table->index(['service_provider_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_availability');
    }
};
