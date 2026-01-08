<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdoptionRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $receiverName;
    public $catName;
    public $reason;
    public $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($adoption)
    {
        $this->receiverName = $adoption->adopter->name;
        $this->catName = $adoption->cat->name;
        $this->reason = $adoption->rejection_reason;
        $this->actionUrl = config('app.frontend_url') . '/cats';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📢 Update Pengajuan Adopsi: ' . $this->catName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.adoption_rejected',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
