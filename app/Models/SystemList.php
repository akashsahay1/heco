<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemList extends Model
{
    protected $fillable = ['list_type', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('list_type', $type)->where('is_active', true)->orderBy('sort_order');
    }
}
