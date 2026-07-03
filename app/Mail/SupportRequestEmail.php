<?php

namespace App\Mail;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportRequestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportRequest $supportRequest)
    {
    }

    public function envelope(): Envelope
    {
        $name = $this->supportRequest->user?->full_name ?? 'a traveller';
        return new Envelope(subject: 'New support request from ' . $name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-request');
    }
}
