<?php

namespace App\Mail;

use App\Models\ServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells HCT a new provider application is waiting in the review queue. */
class AdminNewApplicationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceProvider $provider, public string $providerType)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New partner application: ' . $this->provider->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-application');
    }
}
