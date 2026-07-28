<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Listing caps for provider self-service.
 *
 * The client asked for a ceiling of 10 so a bad-faith signup cannot flood HECO
 * with junk listings. The two catalogues are counted separately: a provider who
 * is both a host and a service supplier runs two of them, and a shared pool
 * would let its taxi rates eat into its experience slots.
 *
 * These live in settings rather than in code so HCT can raise the ceiling for a
 * genuinely large provider without a deploy. 0 disables the cap.
 */
return new class extends Migration {
    private const DEFAULTS = [
        'max_experiences_per_provider' => 10,
        'max_services_per_provider' => 10,
    ];

    public function up(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            if (Setting::getValue($key) === null) {
                Setting::setValue($key, $value, 'providers');
            }
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
