<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody wrote in from the Contact page.
 *
 * The reply-to is the visitor, not HECO's own address: the whole point of a
 * contact form is that the answer goes back by hitting Reply, and a mail that
 * comes from heco@ and replies to heco@ is a letter to nobody.
 */
class ContactMessageEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public string $topic,
        public string $body,
        public ?string $sentFrom = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contact form: ' . $this->topic . ' — ' . $this->name,
            replyTo: [new Address($this->email, $this->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
