<?php

namespace App\Mail;

use App\Models\SpPricing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells HCT a rate is waiting to be reviewed.
 *
 * The approval already writes to the member. Nothing wrote to HCT, so a rate
 * sat in the queue until somebody happened to open the page — and a rate that
 * is not approved is a service that cannot be sold.
 */
class AdminNewPricingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SpPricing $pricing,
        public string $providerName,
        public string $itemLabel,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New rate for review: ' . $this->providerName,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-pricing');
    }
}
