<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'locale', 'flag', 'rate_to_usd', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_to_usd' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
