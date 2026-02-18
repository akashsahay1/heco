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
