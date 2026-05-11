<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $trip)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your HECO Booking is Confirmed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-confirmation');
    }
}
