<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',           'symbol' => '$',   'locale' => 'en-US', 'flag' => 'us', 'rate_to_usd' => 1.0,      'sort_order' => 1],
            ['code' => 'EUR', 'name' => 'Euro',                'symbol' => "\u{20AC}", 'locale' => 'de-DE', 'flag' => 'eu', 'rate_to_usd' => 0.92,     'sort_order' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',       'symbol' => "\u{00A3}", 'locale' => 'en-GB', 'flag' => 'gb', 'rate_to_usd' => 0.79,     'sort_order' => 3],
            ['code' => 'INR', 'name' => 'Indian Rupee',        'symbol' => "\u{20B9}", 'locale' => 'en-IN', 'flag' => 'in', 'rate_to_usd' => 83.0,     'sort_order' => 4],
            ['code' => 'NPR', 'name' => 'Nepalese Rupee',      'symbol' => "\u{0930}\u{0942}", 'locale' => 'ne-NP', 'flag' => 'np', 'rate_to_usd' => 133.0,    'sort_order' => 5],
            ['code' => 'PEN', 'name' => 'Peruvian Sol',        'symbol' => 'S/',  'locale' => 'es-PE', 'flag' => 'pe', 'rate_to_usd' => 3.70,     'sort_order' => 6],
            ['code' => 'AUD', 'name' => 'Australian Dollar',   'symbol' => 'A$',  'locale' => 'en-AU', 'flag' => 'au', 'rate_to_usd' => 1.53,     'sort_order' => 7],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',     'symbol' => 'C$',  'locale' => 'en-CA', 'flag' => 'ca', 'rate_to_usd' => 1.36,     'sort_order' => 8],
            ['code' => 'JPY', 'name' => 'Japanese Yen',        'symbol' => "\u{00A5}", 'locale' => 'ja-JP', 'flag' => 'jp', 'rate_to_usd' => 149.0,    'sort_order' => 9],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',        'symbol' => "\u{00A5}", 'locale' => 'zh-CN', 'flag' => 'cn', 'rate_to_usd' => 7.24,     'sort_order' => 10],
            ['code' => 'KRW', 'name' => 'South Korean Won',    'symbol' => "\u{20A9}", 'locale' => 'ko-KR', 'flag' => 'kr', 'rate_to_usd' => 1320.0,   'sort_order' => 11],
            ['code' => 'THB', 'name' => 'Thai Baht',           'symbol' => "\u{0E3F}", 'locale' => 'th-TH', 'flag' => 'th', 'rate_to_usd' => 35.0,     'sort_order' => 12],
            ['code' => 'SGD', 'name' => 'Singapore Dollar',    'symbol' => 'S$',  'locale' => 'en-SG', 'flag' => 'sg', 'rate_to_usd' => 1.34,     'sort_order' => 13],
            ['code' => 'CHF', 'name' => 'Swiss Franc',         'symbol' => 'CHF', 'locale' => 'de-CH', 'flag' => 'ch', 'rate_to_usd' => 0.88,     'sort_order' => 14],
            ['code' => 'BRL', 'name' => 'Brazilian Real',      'symbol' => 'R$',  'locale' => 'pt-BR', 'flag' => 'br', 'rate_to_usd' => 4.95,     'sort_order' => 15],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
