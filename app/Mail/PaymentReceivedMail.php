<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $receiverName;
    public $catName;
    public $transactionId;
    public $amount;
    public $actionUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($adoption)
    {
        $this->receiverName = $adoption->adopter->name;
        $this->catName = $adoption->cat->name;
        $this->transactionId = $adoption->payment_reference ?? 'REF-' . str_pad($adoption->id, 8, '0', STR_PAD_LEFT);
        $this->amount = $adoption->cat->adoption_fee;
        $this->actionUrl = config('app.frontend_url') . '/dashboard/adoptions/' . $adoption->id;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💰 Pembayaran Diterima: ' . $this->catName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_received',
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
