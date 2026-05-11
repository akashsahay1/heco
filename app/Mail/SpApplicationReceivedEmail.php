<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpApplicationReceivedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $providerType)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your HECO Partner Application Has Been Received');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sp-application-received');
    }
}
