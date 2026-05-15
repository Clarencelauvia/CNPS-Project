<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalRequestApprovedMail extends Mailable
{
    use Queueable, SerializesModels;
    public $user;
    public $rentalRequest;

    public function __construct(RentalRequest $rentalRequest) {
        $this->rentalRequest = $rentalRequest;
        $this->user = $rentalRequest->user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Votre demande de location a été approuvée - CNPS LODGE',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-request-approved',
            with: [
                'name' => $this->user->name,
                'dashboardUrl' => url('/user/dashboard'),
            ]
        );
    }
    public function attachments(): array
    {
        return [];
    }
}