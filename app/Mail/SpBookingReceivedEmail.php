<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpBookingReceivedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $spName,
        public string $roomCategory,
        public ?string $comfortTier,
        public int $quantity,
        public string $date,
        public string $tripId,
        public ?string $travellerName,
        public string $status
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New booking — ' . $this->quantity . ' '
                . $this->roomCategory . ' on ' . $this->date . ' (Trip #' . $this->tripId . ')'
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sp-booking-received');
    }
}
