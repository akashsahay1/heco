<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payment)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment Received - HECO');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-received');
    }
}
