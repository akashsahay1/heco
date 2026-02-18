<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('trip_id')->constrained('trips');
            $table->foreignId('assigned_hct_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('stage', ['follow_up', 'won', 'lost'])->default('follow_up');
            $table->timestamp('enquiry_date')->useCurrent();
            $table->timestamp('last_interaction_date')->nullable();
            $table->enum('interaction_mode', ['call', 'whatsapp', 'email'])->nullable();
            $table->integer('reminder_delay_days')->default(3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
