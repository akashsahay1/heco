<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-person experience pricing by group size (client req 3.2).
 * Each row is a slab: for a party of N travellers, the calculator picks the
 * row with the largest min_persons <= N, so min_persons = 6 naturally serves
 * the "6+ persons" tier (6, 7, 8, …). Extensible — admins can add more slabs
 * (e.g. min_persons = 10) without touching code (req #7).
 * price_per_person is the SELLING price per head at that group size; the
 * experience line = price_per_person(for pax) × pax.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('experience_price_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->constrained('experiences')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_persons');
            $table->decimal('price_per_person', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['experience_id', 'min_persons']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_price_slabs');
    }
};
