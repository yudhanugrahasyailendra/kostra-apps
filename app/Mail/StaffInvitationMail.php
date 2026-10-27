<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StaffInvitationMail extends Mailable
{
    public function __construct(
        public readonly string $url,
        public readonly string $tenantName,
        public readonly string $inviterName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Undangan bergabung di {$this->tenantName} (Kostra)");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invitation');
    }
}
