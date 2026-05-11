<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileUpdatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string,mixed>  $changes  Map of field label => new value (only changed fields)
     */
    public function __construct(public string $name, public array $changes, public string $when)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your HECO Profile Was Updated');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.profile-updated');
    }
}
