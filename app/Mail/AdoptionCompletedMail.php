<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdoptionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adopterName;
    public $shelterName;
    public $catName;
    public $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($adoption)
    {
        $this->adopterName = $adoption->adopter->name;
        $this->shelterName = $adoption->cat->shelter->name;
        $this->catName = $adoption->cat->name;
        $this->actionUrl = config('app.frontend_url') . '/cats/' . $adoption->cat->id;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Adopsi Selesai: ' . $this->catName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.adoption_completed',
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
