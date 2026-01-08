<?php

namespace App\Console\Commands;

use App\Models\Adoption;
use App\Models\Notification;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckShippingDeadline extends Command
{
    protected $signature = 'adoptions:check-shipping-deadline';
    protected $description = 'Cek adopsi yang melewati batas waktu pengiriman dan otomatis batalkan';

    public function handle()
    {
        $this->info('Mengecek adopsi yang melewati batas waktu pengiriman...');

        // Cari adopsi yang sudah dibayar tapi belum dikirim dan deadline terlewati
        $overdueAdoptions = Adoption::where('status', 'payment')
            ->whereNotNull('shipping_deadline')
            ->where('shipping_deadline', '<', now())
            ->whereNull('shipped_at')
            ->with(['cat.shelter.user', 'adopter', 'escrowTransaction'])
            ->get();

        $this->info("Ditemukan {$overdueAdoptions->count()} adopsi yang melewati deadline.");

        foreach ($overdueAdoptions as $adoption) {
            $this->cancelOverdueAdoption($adoption);
        }

        $this->info('Selesai.');
        return 0;
    }

    protected function cancelOverdueAdoption($adoption)
    {
        Log::info("Auto-cancel adopsi #{$adoption->id} karena melewati deadline pengiriman.");

        // Update status
        $adoption->update(['status' => 'cancelled']);

        // Refund escrow (mark as refunded)
        if ($adoption->escrowTransaction) {
            $adoption->escrowTransaction->update(['payment_status' => 'refunded']);
        }

        // Kembalikan status kucing ke available
        if ($adoption->cat) {
            $adoption->cat->update(['adoption_status' => 'available']);
        }

        // Notifikasi ke Adopter
        Notification::notify(
            $adoption->adopter_id,
            'adoption_cancelled',
            '❌ Adopsi Dibatalkan',
            "Maaf, adopsi {$adoption->cat->name} dibatalkan karena shelter tidak mengirim dalam 3 hari. Dana akan dikembalikan.",
            '/dashboard',
            $adoption
        );

        // Notifikasi ke Shelter
        Notification::notify(
            $adoption->cat->shelter->user_id,
            'adoption_cancelled',
            '❌ Adopsi Dibatalkan Otomatis',
            "Adopsi {$adoption->cat->name} dibatalkan karena tidak mengirim dalam 3 hari. Dana dikembalikan ke adopter.",
            '/dashboard',
            $adoption
        );

        // WA ke Adopter
        if ($adoption->adopter->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->adopter->phone,
                "❌ Maaf, adopsi {$adoption->cat->name} dibatalkan karena shelter tidak mengirim dalam 3 hari. Dana akan dikembalikan. - MangOyen"
            )->delay(now()->addSeconds(5));
        }

        // WA ke Shelter
        if ($adoption->cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->cat->shelter->user->phone,
                "❌ Adopsi {$adoption->cat->name} dibatalkan karena tidak dikirim dalam 3 hari. Dana dikembalikan ke adopter. - MangOyen"
            )->delay(now()->addSeconds(15));
        }

        $this->line("  ✓ Adopsi #{$adoption->id} ({$adoption->cat->name}) dibatalkan.");
    }
}
