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

    /**
     * @param string        $name            Contact or display name.
     * @param string        $providerType    The role labels, for reading aloud.
     * @param ?string       $setPasswordUrl  Only when they never set one.
     * @param array<string> $types           hlh / osp / hrp — what they may
     *        actually do. The label is a sentence and cannot be branched on,
     *        and the three have different work waiting for them: a host lists
     *        their place, a service provider prices theirs, and a regional
     *        partner sells nothing at all. Telling all three to fill in an
     *        availability calendar so HCT could send them on trips was true of
     *        one of them.
     */
    public function __construct(
        public string $name,
        public string $providerType,
        public ?string $setPasswordUrl = null,
        public array $types = [],
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
