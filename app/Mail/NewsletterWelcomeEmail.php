<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $email, public string $homeUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to HECO — you’re on the list');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.newsletter-welcome');
    }
}
