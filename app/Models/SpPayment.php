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

    /**
     * What HECO has paid on this bill, less what the provider gave back.
     * $exceptEntryId leaves one entry out, to check an edit of it.
     */
    public function netPaid(?int $exceptEntryId = null): float
    {
        $entries = $this->entries()->when($exceptEntryId, fn ($q) => $q->where('id', '!=', $exceptEntryId));
        $paid = (float) (clone $entries)->where('direction', SpPaymentEntry::PAID)->sum('amount');
        $returned = (float) (clone $entries)->where('direction', SpPaymentEntry::RETURNED)->sum('amount');

        return round($paid - $returned, 2);
    }

    /**
     * What the provider owes HECO back on this bill: paid more than the bill
     * now comes to, as when a trip is cancelled for a smaller charge than was
     * paid in advance. Kept apart from balance, which never goes below 0: a
     * provider's next payout adds their balances up, and a minus on one bill
     * would quietly take it off what another trip owes them.
     */
    public function owesBack(): float
    {
        return max(0, round((float) $this->amount_paid - (float) $this->amount_due, 2));
    }
}
