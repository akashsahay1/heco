<?php

namespace App\Mail;

use App\Models\Experience;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells HCT a listing is waiting to be reviewed.
 *
 * A new partner application has always announced itself this way. An
 * experience did not: it joined the pending queue quietly, and was found only
 * by someone opening that page on the off chance. A member who has spent an
 * evening describing their house should not wait on that.
 */
class AdminNewExperienceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Experience $experience, public string $providerName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New experience for review: ' . $this->experience->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-experience');
    }
}
