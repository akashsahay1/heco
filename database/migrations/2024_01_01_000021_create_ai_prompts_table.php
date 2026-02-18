<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 100)->unique();
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->string('model', 100)->default('mistral');
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(4096);
            $table->string('response_format', 50)->default('json');
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
