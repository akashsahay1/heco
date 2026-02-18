<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpPayment extends Model
{
    protected $fillable = [
        'trip_id', 'service_provider_id', 'service_type',
        'amount_due', 'amount_paid', 'balance', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function serviceProvider()
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function entries()
    {
        return $this->hasMany(SpPaymentEntry::class)->orderBy('payment_date');
    }
}
