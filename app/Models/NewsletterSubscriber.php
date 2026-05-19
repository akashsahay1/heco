<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'user_id',
        'is_customer',
        'source',
        'ip_address',
        'subscribed_at',
        'unsubscribed_at',
        'last_emailed_at',
    ];

    protected $casts = [
        'is_customer'      => 'boolean',
        'subscribed_at'    => 'datetime',
        'unsubscribed_at'  => 'datetime',
        'last_emailed_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
