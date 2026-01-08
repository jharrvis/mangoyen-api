<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\EscrowTransaction;
use App\Models\ActivityLog;
use App\Services\MidtransService;
use App\Models\Notification;
use App\Mail\PaymentReceivedMail;
use App\Mail\InvoiceMail;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Get Snap Token for an adoption
     */
    public function createSnapToken(Request $request, $adoptionId)
    {
        $user = $request->user();
        $adoption = Adoption::with(['escrowTransaction', 'cat', 'adopter'])->findOrFail($adoptionId);

        if ($adoption->adopter_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = $adoption->escrowTransaction;

        if (!$transaction) {
            return response()->json(['message' => 'Escrow transaction not found'], 404);
        }

        // Return existing token if valid (optional optimization)
        if ($transaction->snap_token && $transaction->expires_at && $transaction->expires_at->isFuture()) {
            return response()->json(['snap_token' => $transaction->snap_token]);
        }

        $snapToken = $this->midtransService->createSnapToken($transaction);

        if (!$snapToken) {
            return response()->json(['message' => 'Failed to generate payment token'], 500);
        }

        return response()->json(['snap_token' => $snapToken]);
    }

    /**
     * Handle Midtrans Webhook Notification
     */
    public function webhook(Request $request)
    {
        $payload = $request->all();

        // Log incoming webhook for debugging
        Log::info('Midtrans Webhook Received', ['payload' => $payload]);

        // Handle Midtrans test webhook (when testing from dashboard)
        if (empty($payload) || !isset($payload['order_id'])) {
            Log::info('Midtrans Webhook Test - Empty or Test Payload');
            return response()->json(['status' => 'ok', 'message' => 'Webhook endpoint is working']);
        }

        $serverKey = config('services.midtrans.server_key');

        // Verify Signature
        $expectedSignature = hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey);

        if ($expectedSignature !== ($payload['signature_key'] ?? '')) {
            Log::warning('Midtrans Webhook Invalid Signature', [
                'order_id' => $payload['order_id'] ?? 'unknown',
                'expected' => substr($expectedSignature, 0, 20) . '...',
                'received' => substr($payload['signature_key'] ?? '', 0, 20) . '...',
            ]);
            return response()->json(['message' => 'Invalid Signature'], 403);
        }

        $orderId = $payload['order_id'];
        $transactionStatus = $payload['transaction_status'];
        $type = $payload['payment_type'];
        $fraudId = $payload['fraud_status'] ?? null;

        // Handle Midtrans test notifications (order_id starts with "payment_notif_test_")
        if (str_starts_with($orderId, 'payment_notif_test_')) {
            Log::info('Midtrans Webhook Test Notification Received', ['order_id' => $orderId]);
            return response()->json(['status' => 'ok', 'message' => 'Test notification received']);
        }

        $transaction = EscrowTransaction::where('midtrans_order_id', $orderId)->first();

        if (!$transaction) {
            Log::warning('Midtrans Webhook Transaction Not Found', ['order_id' => $orderId]);
            return response()->json(['status' => 'ok', 'message' => 'Transaction not found but acknowledged'], 200);
        }

        $adoption = $transaction->adoption;

        if ($transactionStatus == 'capture') {
            if ($type == 'credit_card') {
                if ($fraudId == 'challenge') {
                    $transaction->update(['payment_status' => 'pending']);
                } else {
                    $this->markAsPaid($transaction, $type, $payload['transaction_id']);
                }
            }
        } else if ($transactionStatus == 'settlement') {
            $this->markAsPaid($transaction, $type, $payload['transaction_id']);
        } else if ($transactionStatus == 'pending') {
            $transaction->update(['payment_status' => 'pending']);
        } else if ($transactionStatus == 'deny') {
            $transaction->update(['payment_status' => 'failed']);
        } else if ($transactionStatus == 'expire') {
            $transaction->update(['payment_status' => 'expired']);
        } else if ($transactionStatus == 'cancel') {
            $transaction->update(['payment_status' => 'cancelled']);
        }

        return response()->json(['status' => 'success']);
    }

    protected function markAsPaid($transaction, $method, $reference)
    {
        if ($transaction->isPaid())
            return;

        $transaction->markAsPaid($method, $reference);
        $adoption = $transaction->adoption;
        $adoption->update([
            'status' => 'payment',
            'shipping_deadline' => now()->addDays(3) // 3 hari untuk kirim
        ]);

        // Log activity
        ActivityLog::create([
            'causer_id' => $adoption->adopter_id,
            'subject_type' => 'App\Models\Adoption',
            'subject_id' => $adoption->id,
            'event' => 'payment_verified',
            'description' => "Pembayaran adopsi {$adoption->cat->name} telah diverifikasi. Metode: {$method}",
            'properties' => [
                'adoption_id' => $adoption->id,
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'payment_method' => $method,
                'reference' => $reference
            ]
        ]);

        // Auto-close competing adoptions for the same cat
        $catId = $adoption->cat_id;
        $competingAdoptions = Adoption::with(['adopter', 'cat'])
            ->where('cat_id', $catId)
            ->where('id', '!=', $adoption->id)
            ->whereIn('status', ['pending', 'approved', 'waiting_payment'])
            ->get();

        foreach ($competingAdoptions as $competing) {
            $competing->update(['status' => 'cancelled']);

            // Notify affected user
            Notification::notify(
                $competing->adopter_id,
                'adoption_cancelled',
                '❌ Pengajuan Adopsi Ditutup',
                "Pengajuan adopsi untuk {$competing->cat->name} ditutup karena sudah ada adopter lain yang melakukan pembayaran terlebih dahulu.",
                '/dashboard',
                $competing
            );

            // Log cancellation activity
            ActivityLog::create([
                'causer_id' => null, // System action
                'subject_type' => 'App\Models\Adoption',
                'subject_id' => $competing->id,
                'event' => 'adoption_auto_cancelled',
                'description' => "Pengajuan adopsi {$competing->cat->name} oleh {$competing->adopter->name} dibatalkan otomatis karena ada pembayaran dari adopter lain",
                'properties' => [
                    'adoption_id' => $competing->id,
                    'cat_id' => $catId,
                    'reason' => 'competing_payment_verified',
                    'winning_adoption_id' => $adoption->id
                ]
            ]);

            // Send WhatsApp notification to affected user
            if ($competing->adopter->phone) {
                SendWhatsAppMessage::dispatch(
                    $competing->adopter->phone,
                    "Maaf, pengajuan adopsi untuk {$competing->cat->name} ditutup karena sudah ada adopter lain yang melakukan pembayaran. Terima kasih atas minatnya! - MangOyen"
                )->delay(now()->addSeconds(10));
            }
        }

        // Send Notifications (Email & WA)
        $this->sendPaymentNotifications($adoption);
    }

    protected function sendPaymentNotifications($adoption)
    {
        $adoption->load(['cat.shelter.user', 'adopter', 'escrowTransaction']);

        // === INVOICE EMAIL TO ADOPTER (USER) ===
        if ($adoption->adopter->email) {
            Mail::to($adoption->adopter->email)->queue(new InvoiceMail($adoption, 'adopter'));
        }

        // === INVOICE EMAIL TO SHELTER ===
        if ($adoption->cat->shelter->user->email) {
            Mail::to($adoption->cat->shelter->user->email)->queue(new InvoiceMail($adoption, 'shelter'));
        }

        // WA to Shelter
        if ($adoption->cat->shelter->user->phone) {
            SendWhatsAppMessage::dispatch(
                $adoption->cat->shelter->user->phone,
                "💰 Pembayaran telah diterima untuk adopsi {$adoption->cat->name}. Dana aman di Rekber MangOyen. Silakan proses pengiriman anabul!"
            )->delay(now()->addSeconds(5));
        }

        // Push notification in app
        Notification::notify(
            $adoption->cat->shelter->user_id,
            'payment_received',
            '💰 Pembayaran Diterima',
            "Pembayaran untuk {$adoption->cat->name} telah diverifikasi. Silakan hubungi adopter untuk pengiriman.",
            '/dashboard',
            $adoption
        );
    }
}
