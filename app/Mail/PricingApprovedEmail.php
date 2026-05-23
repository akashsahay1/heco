<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PricingApprovedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string      $name      Provider contact/display name
     * @param string      $itemLabel What the pricing row is for (description / service)
     * @param string      $newPrice  Approved price, pre-formatted number (no symbol)
     * @param string|null $unit      Pricing unit (e.g. "per night", "per person")
     * @param string|null $oldPrice  Previous price for an edit, pre-formatted (null for new rows)
     */
    public function __construct(
        public string $name,
        public string $itemLabel,
        public string $newPrice,
        public ?string $unit = null,
        public ?string $oldPrice = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your pricing update has been approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pricing-approved');
    }
}
