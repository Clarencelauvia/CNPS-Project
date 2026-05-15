<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalRequestRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $rentalRequest;
    public $user;
    public $reason;

    public function __construct(RentalRequest $rentalRequest, $reason) {
        $this->rentalRequest = $rentalRequest;
        $this->user = $rentalRequest->user;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ Votre demande de location a été rejetée  CNPS LODGE',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-request-rejected',
            with: [
                'name' => $this->user->name,
                'reason' => $this->reason,
            ]
        );
    }
    public function attachments(): array
    {
        return [];
    }
}