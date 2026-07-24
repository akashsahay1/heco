<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpApplicationApprovedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $providerType,
        public ?string $setPasswordUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your HECO Partner Application Has Been Approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sp-application-approved');
    }
}
