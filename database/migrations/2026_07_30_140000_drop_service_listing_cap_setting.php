<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Only experiences are capped.
 *
 * The earlier migration created a ceiling for each catalogue. The client has
 * since been explicit that the limit of ten belongs to experiences alone —
 * a host may list ten — and that a supplier's rate card is not limited. A taxi
 * operator with several vehicles priced for both plains and hills passes ten
 * without doing anything unusual, and the cap exists to keep HCT's review queue
 * sane, not to ration rates.
 *
 * The setting row goes with the rule. The control panel renders whatever rows
 * exist in a group, so leaving it behind would show HCT a field that quietly
 * governs nothing.
 */
return new class extends Migration {
    public function up(): void
    {
        Setting::where('key', 'max_services_per_provider')->delete();
    }

    public function down(): void
    {
        if (Setting::getValue('max_services_per_provider') === null) {
            Setting::setValue('max_services_per_provider', 10, 'providers');
        }
    }
};
