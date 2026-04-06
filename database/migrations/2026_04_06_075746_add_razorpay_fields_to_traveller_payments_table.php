<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('traveller_payments', function (Blueprint $table) {
            $table->string('razorpay_order_id')->nullable()->after('recorded_by');
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
            $table->string('razorpay_signature')->nullable()->after('razorpay_payment_id');
            $table->string('payment_status')->default('pending')->after('razorpay_signature');
        });
    }

    public function down(): void
    {
        Schema::table('traveller_payments', function (Blueprint $table) {
            $table->dropColumn(['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'payment_status']);
        });
    }
};
