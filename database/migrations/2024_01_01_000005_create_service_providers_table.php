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

            // Every role this member holds. A host that also runs a taxi is an
            // HLH and an OSP, and asking for one of them hides half of what
            // they signed up for. Where a single type has to be shown, the
            // first of the set is it — see getProviderTypeAttribute().
            $table->json('provider_types')->nullable();
            // Nullable on purpose: null means "never asked", which is a
            // different answer from a member who said No.
            $table->boolean('has_business')->nullable();
            // What they offer, one list per role held.
            $table->json('experience_categories')->nullable();
            $table->json('service_categories')->nullable();
            // The catch-all the client asked for, so an OSP is never turned
            // away for doing something unlisted.
            $table->string('other_services')->nullable();

            $table->string('business_type')->nullable();
            $table->string('registration_number')->nullable();
            $table->unsignedSmallInteger('year_established')->nullable();

            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->unique();
            $table->string('phone_1', 20);
            $table->string('phone_2', 20)->nullable();

            // Which travellers they can host.
            $table->boolean('speaks_english')->default(false);
            $table->boolean('speaks_hindi')->default(false);
            $table->string('other_languages')->nullable();
            // "Many users won't regularly check their email."
            $table->boolean('contact_by_email')->default(true);
            $table->boolean('contact_by_whatsapp')->default(true);

            $table->foreignId('region_id')->constrained('regions');
            // `address` is the street line; the three below complete it.
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('upi')->nullable();

            $table->text('services_offered')->nullable();
            $table->text('accommodation_categories')->nullable();
            $table->text('vehicle_types')->nullable();
            $table->json('guide_types')->nullable();
            $table->text('activity_types')->nullable();

            // A regional partner sells no catalogue, so their background is
            // what the application is judged on.
            $table->string('education_level')->nullable();
            $table->text('education_notes')->nullable();
            $table->string('english_level')->nullable();
            $table->string('computer_skill_level')->nullable();
            // A list of roles rather than one blob — "work experiences" is
            // plural in the spec, and HCT reads them one by one.
            $table->json('work_experience')->nullable();
            $table->text('causes_note')->nullable();
            $table->text('community_note')->nullable();

            // Verification documents from the application (ID proof,
            // registration, permits, photos), as { label, path, original_name }.
            $table->json('documents')->nullable();

            $table->text('notes')->nullable();
            $table->string('ical_url', 500)->nullable();
            $table->timestamp('ical_last_synced_at')->nullable();

            // banned and hidden are both out of service to a traveller, and the
            // member is never told which: banned blocks them entirely, hidden
            // leaves them working but off the shelf. removed survives only for
            // rows filed before removal became a delete.
            $table->enum('status', ['pending', 'approved', 'rejected', 'removed', 'banned', 'hidden'])
                ->default('pending');
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            // Who touched the record last, and from which side of the app —
            // HCT editing a provider and the provider editing themselves are
            // the same column and different stories.
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_updated_by_role', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
