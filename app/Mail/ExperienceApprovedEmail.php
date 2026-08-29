<?php

namespace App\Mail;

use App\Models\Experience;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member their listing is live.
 *
 * Until now nothing did. A member submitted an experience, HCT approved it
 * whenever they got to it, and the listing began selling without a word — the
 * only way to find out was to open the app and look. A rate card has been
 * telling them this all along; an experience is the larger piece of work and
 * was the one going unremarked.
 */
class ExperienceApprovedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public Experience $experience)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your experience is live: ' . $this->experience->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.experience-approved');
    }
}
