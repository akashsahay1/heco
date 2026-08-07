<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            // Null means the account was created for them and they have not
            // chosen a password yet — approval mails those people a link.
            $table->timestamp('password_set_at')->nullable();
            $table->enum('auth_type', ['email', 'google', 'facebook'])->default('email');
            // One role for every partner. Which kinds of partner they are —
            // host, supplier, regional — lives in
            // service_providers.provider_types, which can hold more than one.
            $table->enum('user_role', ['administrator', 'collaborator', 'traveller', 'provider'])->default('traveller');
            $table->string('mobile', 20)->nullable();
            $table->string('address1', 500)->nullable();
            $table->string('address2', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            // Citizenship, stored verbatim from config/countries.php. Distinct
            // from `country`, which is where they live: this one decides the
            // trip's traveller_origin pricing bucket, Indian vs foreigner.
            $table->string('nationality', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('gender', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('avatar')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('newsletter_optin')->default(false);
            $table->boolean('portal_notify_optin')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();

            // Unique per role, not per address. One person is often both a
            // traveller and a provider, and both accounts are real — what must
            // not happen is two of the same kind on one address.
            $table->unique(['email', 'user_role'], 'users_email_role_unique');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
