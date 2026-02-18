<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravellerPayment extends Model
{
    protected $fillable = [
        'trip_id', 'user_id', 'amount', 'payment_date', 'mode', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
