<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProjectInvitationMail extends Mailable
{
    public function __construct(public ProjectInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to {$this->invitation->project->name} on ".config('app.name'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.project-invitation', with: [
            'url' => route('projects.index'),
        ]);
    }
}
