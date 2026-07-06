<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'gst_percent', 'value' => '5', 'group' => 'financial'],
            ['key' => 'default_rp_margin_percent', 'value' => '5', 'group' => 'financial'],
            ['key' => 'default_hrp_margin_percent', 'value' => '10', 'group' => 'financial'],
            ['key' => 'default_hct_commission_percent', 'value' => '15', 'group' => 'financial'],
            ['key' => 'base_transport_per_day', 'value' => '3500', 'group' => 'financial'],
            ['key' => 'base_accommodation_per_night', 'value' => '2500', 'group' => 'financial'],
            ['key' => 'base_guide_per_day', 'value' => '2000', 'group' => 'financial'],
            // Pax-type pricing (#42): a child bills at child_price_percent of an
            // adult, an infant at infant_price_percent. Editable in Settings.
            ['key' => 'child_price_percent', 'value' => '50', 'group' => 'financial'],
            ['key' => 'infant_price_percent', 'value' => '0', 'group' => 'financial'],
            // Per-provider markup (req 3.3): global fallback when a provider has no
            // markup_percent set. 0 = no markup until an admin sets one.
            ['key' => 'default_provider_markup_percent', 'value' => '0', 'group' => 'financial'],
            ['key' => 'site_name', 'value' => 'HECO Portal', 'group' => 'general'],
            ['key' => 'site_email', 'value' => 'info@heco.eco', 'group' => 'general'],
            ['key' => 'ollama_enabled', 'value' => '1', 'group' => 'ai'],
            ['key' => 'default_ai_model', 'value' => 'mistral', 'group' => 'ai'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
