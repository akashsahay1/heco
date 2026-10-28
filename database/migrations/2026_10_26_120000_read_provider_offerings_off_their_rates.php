<?php

use App\Models\ServiceProvider;
use Illuminate\Database\Migrations\Migration;

/**
 * Services offered, comfort tiers, vehicle, guide and activity types are read
 * off each provider's approved, switched-on rates from now on, rather than
 * typed in three places beside the rates (ServiceProvider::syncOfferings).
 * This brings every existing provider into line once.
 */
return new class extends Migration {
    public function up(): void
    {
        ServiceProvider::query()->each(fn (ServiceProvider $sp) => $sp->syncOfferings());
    }

    public function down(): void
    {
        // Nothing to undo: the values are derived, and the old ones were what
        // the rates disagreed with.
    }
};
