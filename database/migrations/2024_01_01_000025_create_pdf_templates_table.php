<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pdf_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 100)->unique();
            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->text('css')->nullable();
            $table->string('paper_size', 20)->default('A4');
            $table->enum('orientation', ['portrait', 'landscape'])->default('portrait');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_templates');
    }
};
