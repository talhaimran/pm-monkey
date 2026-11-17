<?php

namespace App\Mail;

use App\Models\WorkspaceInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Every invitation email, to a workspace with or without a project (class name kept from before step 0). */
class ProjectInvitationMail extends Mailable
{
    /** $token is the plain link token; only its hash is stored. */
    public function __construct(public WorkspaceInvitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        $to = $this->invitation->project?->name ?? $this->invitation->workspace->name;

        return new Envelope(subject: "You're invited to {$to} on ".config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.project-invitation', with: [
            'url' => route('invitations.show', $this->token),
        ]);
    }
}
