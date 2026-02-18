<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpPaymentEntry extends Model
{
    protected $fillable = [
        'sp_payment_id', 'amount', 'payment_date', 'mode', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function spPayment()
    {
        return $this->belongsTo(SpPayment::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
