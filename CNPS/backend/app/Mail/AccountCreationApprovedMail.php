<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCreationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $tempPassword) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre compte CNPS LODGE a été créé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-creation-approved',
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'password' => $this->tempPassword,
                'loginUrl' => url('/login/user'),
            ]
        );
    }
}