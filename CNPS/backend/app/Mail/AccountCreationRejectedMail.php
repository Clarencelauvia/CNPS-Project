<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCreationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre demande de création de compte a été rejetée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-creation-rejected',
            with: [
                'name' => $this->user->name,
                'reason' => $this->reason,
            ]
        );
    }
}