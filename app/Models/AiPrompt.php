<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    protected $fillable = [
        'name', 'key', 'system_prompt', 'user_prompt_template', 'model',
        'temperature', 'max_tokens', 'response_format', 'is_active', 'version', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
