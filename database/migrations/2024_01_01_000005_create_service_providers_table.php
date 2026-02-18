<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('provider_type', ['hrp', 'hlh', 'osp']);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email');
            $table->string('phone_1', 20);
            $table->string('phone_2', 20)->nullable();
            $table->foreignId('region_id')->constrained('regions');
            $table->text('address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('upi')->nullable();
            $table->text('services_offered')->nullable();
            $table->text('accommodation_categories')->nullable();
            $table->text('vehicle_types')->nullable();
            $table->text('activity_types')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
