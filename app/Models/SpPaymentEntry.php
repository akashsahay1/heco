<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpPaymentEntry extends Model
{
    protected $fillable = [
        'sp_payment_id', 'amount', 'direction', 'payment_date', 'mode', 'notes', 'recorded_by',
    ];

    /** Money HECO paid the provider, or money the provider gave back. */
    public const PAID = 'paid';
    public const RETURNED = 'returned';

    /** The amount as it counts towards what HECO has paid: a return counts against. */
    public function signedAmount(): float
    {
        return $this->direction === self::RETURNED ? -(float) $this->amount : (float) $this->amount;
    }

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
