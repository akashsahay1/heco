<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->integer('day_number');
            $table->string('title', 255)->nullable();
            $table->text('short_description')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('inclusions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_days');
    }
};
