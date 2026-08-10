<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Optional extras a supplier hangs off one of their rates.
     *
     * The client's data-collection document asks for add-ons on a standard
     * accommodation, and they only existed on experiences — so a hotel could
     * price a room but never an extra bed or an airport pickup.
     *
     * Its own table rather than a nullable owner on experience_addons: a rate's
     * extras and an experience's extras are reviewed on different queues and
     * die with different parents, and one table with two possible owners means
     * every read has to ask which kind it is holding.
     */
    public function up(): void
    {
        Schema::create('sp_pricing_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sp_pricing_id')->constrained('sp_pricing')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Nullable: a supplier may include something at no charge, and
            // "free" is a different statement from "we have not priced it yet".
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sp_pricing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_pricing_addons');
    }
};
