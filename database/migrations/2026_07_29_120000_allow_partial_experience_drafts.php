<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a draft be half-finished.
 *
 * The client asked for drafts because "many users won't have all the
 * information or photos ready in one session". The app honoured that — its
 * "Save draft" button asks only for a name and a category — but these four
 * columns were NOT NULL, so the insert failed and the host's work was lost with
 * nothing on screen to explain it.
 *
 * A draft is never published and never reviewed: is_active stays false and it
 * does not enter HCT's queue. Submitting for review still validates in full, so
 * nothing incomplete can reach a traveller through this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->change();
            $table->string('type', 100)->nullable()->change();
            $table->string('short_description', 500)->nullable()->change();
            $table->enum('duration_type', ['less_than_day', 'single_day', 'multi_day'])
                ->nullable()->change();
        });
    }

    public function down(): void
    {
        // Anything still incomplete would block the rollback, so give the
        // columns something valid before making them required again.
        \App\Models\Experience::whereNull('region_id')
            ->orWhereNull('type')
            ->orWhereNull('short_description')
            ->orWhereNull('duration_type')
            ->get()
            ->each(function ($experience) {
                $experience->forceFill([
                    'region_id' => $experience->region_id
                        ?: \App\Models\Region::query()->value('id'),
                    'type' => $experience->type ?: 'Cultural Immersion',
                    'short_description' => $experience->short_description ?: '(draft)',
                    'duration_type' => $experience->duration_type ?: 'single_day',
                ])->save();
            });

        Schema::table('experiences', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable(false)->change();
            $table->string('type', 100)->nullable(false)->change();
            $table->string('short_description', 500)->nullable(false)->change();
            $table->enum('duration_type', ['less_than_day', 'single_day', 'multi_day'])
                ->nullable(false)->change();
        });
    }
};
