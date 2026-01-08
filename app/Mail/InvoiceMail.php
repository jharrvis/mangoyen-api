<?php

namespace App\Mail;

use App\Models\Adoption;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $adoption;
    public $recipientName;
    public $recipientType; // 'adopter' or 'shelter'
    public $invoiceNumber;
    public $invoiceData;

    /**
     * Create a new message instance.
     */
    public function __construct(Adoption $adoption, string $recipientType = 'adopter')
    {
        $this->adoption = $adoption;
        $this->recipientType = $recipientType;

        $transaction = $adoption->escrowTransaction;

        $this->invoiceNumber = $transaction?->midtrans_order_id
            ?? 'INV-' . $adoption->id . '-' . strtoupper(base_convert(strtotime($adoption->created_at), 10, 36));

        $this->recipientName = $recipientType === 'adopter'
            ? $adoption->adopter->name
            : $adoption->cat->shelter->name;

        $this->invoiceData = [
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $adoption->created_at->format('d F Y'),
            'isPaid' => $transaction?->isPaid() ?? false,
            'adopterName' => $adoption->adopter->name,
            'adopterPhone' => $adoption->adopter_phone ?? '-',
            'adopterEmail' => $adoption->adopter->email ?? '-',
            'shelterName' => $adoption->cat->shelter->name,
            'catName' => $adoption->cat->name,
            'catBreed' => $adoption->cat->breed ?? '-',
            'catAge' => $adoption->cat->age_category ?? '-',
            'amount' => 'Rp ' . number_format($transaction?->amount ?? $adoption->cat->adoption_fee ?? 0, 0, ',', '.'),
            'paymentMethod' => $transaction?->payment_method,
            'paidAt' => $transaction?->paid_at?->format('d M Y H:i'),
            'transactionId' => $transaction?->midtrans_transaction_id ?? $transaction?->payment_reference,
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice Adopsi {$this->adoption->cat->name} - {$this->invoiceNumber}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'recipientName' => $this->recipientName,
                'recipientType' => $this->recipientType,
                'invoiceNumber' => $this->invoiceNumber,
                'catName' => $this->adoption->cat->name,
                'amount' => $this->invoiceData['amount'],
                'isPaid' => $this->invoiceData['isPaid'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.invoice', $this->invoiceData);

        return [
            Attachment::fromData(fn() => $pdf->output(), "Invoice-{$this->invoiceNumber}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
